<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\MetodosPago;
use App\Models\Envio;
use App\Models\Direccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PagosController extends Controller
{
    // 🔧 MÉTODOS HELPER PARA SNAPSHOTS
    
    /**
     * Crear snapshot de dirección para envios (optimizado)
     */
    private function createAddressSnapshot(?Direccion $direccion): array
    {
        if (!$direccion) {
            return [
                'direccion_linea1_snapshot' => null,
                'direccion_linea2_snapshot' => null,
                'direccion_ciudad_snapshot' => null,
                'direccion_estado_snapshot' => null
            ];
        }

        return [
            'direccion_linea1_snapshot' => $direccion->calle . ' ' . $direccion->numero,
            'direccion_linea2_snapshot' => $direccion->referencia,
            'direccion_ciudad_snapshot' => $direccion->ciudad,
            'direccion_estado_snapshot' => $direccion->distrito
        ];
    }

    /**
     * Crear snapshot de transportista para envios (optimizado)
     */
    private function createTransportistSnapshot($transportista): array
    {
        if (!$transportista) {
            return [
                'transportista_nombre_snapshot' => null,
                'transportista_telefono_snapshot' => null
            ];
        }

        return [
            'transportista_nombre_snapshot' => $transportista->nombre ?? null,
            'transportista_telefono_snapshot' => $transportista->telefono ?? null
        ];
    }

    /**
     * Obtener todos los métodos de pago activos
     * GET /payment-methods
     */    public function getPaymentMethods()
    {
        try {
            $paymentMethods = MetodosPago::where('activo', true)
                ->select('id_metodo_pago', 'nombre')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'message' => 'Métodos de pago obtenidos exitosamente',
                'payment_methods' => $paymentMethods
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener métodos de pago'
            ], 500);
        }
    }

    /**
     * Crear un nuevo pago
     * POST /payments
     */
    public function store(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'pedido_id' => 'required|integer|exists:pedidos,id_pedido',
                'metodo_pago_id' => 'required|integer|exists:metodos_pago,id_metodo_pago',
                'monto_pago' => 'required|numeric|min:0.01',
                'referencia_pago' => 'nullable|string|max:255',
                'estado_pago' => 'sometimes|string|in:pendiente,completado,fallido,cancelado'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Verificar que el pedido pertenece al usuario autenticado
            $pedido = Pedido::where('id_pedido', $request->pedido_id)
                           ->where('usuarios_id_usuario', $user->id_usuario)
                           ->first();

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado o no autorizado'
                ], 404);
            }            // Verificar que el método de pago está activo
            $metodoPago = MetodosPago::where('id_metodo_pago', $request->metodo_pago_id)
                                   ->where('activo', true)
                                   ->first();

            if (!$metodoPago) {
                return response()->json([
                    'error' => 'Método de pago no válido o inactivo'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // 🔧 Crear snapshot del método de pago
                $metodoPago = MetodosPago::find($request->metodo_pago_id);
                $paymentMethodSnapshot = [
                    'metodo_pago_nombre_snapshot' => $metodoPago->nombre
                ];
                
                // Crear el pago con snapshot
                $pago = Pago::create(array_merge([
                    'fecha_pago' => now(),
                    'monto_pago' => $request->monto_pago,
                    'estado_pago' => $request->get('estado_pago', 'pendiente'),
                    'referencia_pago' => $request->referencia_pago,
                    'pedidos_id_pedido' => $request->pedido_id,
                    'metodos_pago_id_metodo_pago' => $request->metodo_pago_id
                ], $paymentMethodSnapshot));

                // Si el pago es completado, actualizar el estado del pedido
                if ($request->get('estado_pago') === 'completado') {
                    $pedido->update(['estado' => 'pagado']);
                }

                DB::commit();

                // Cargar relaciones para la respuesta
                $pago->load(['pedido', 'metodos_pago']);

                return response()->json([
                    'message' => 'Pago creado exitosamente',
                    'pago' => $pago
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Error al crear pago: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al procesar el pago'
            ], 500);
        }
    }

    /**
     * Obtener los pagos del usuario autenticado
     * GET /me/payments
     */
    public function getUserPayments(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $query = Pago::with(['pedido', 'metodos_pago'])
                         ->whereHas('pedido', function ($q) use ($user) {
                             $q->where('usuarios_id_usuario', $user->id_usuario);
                         });

            // Filtros opcionales
            if ($request->has('estado')) {
                $query->where('estado_pago', $request->get('estado'));
            }

            if ($request->has('desde') && $request->has('hasta')) {
                $query->whereBetween('fecha_pago', [
                    $request->get('desde'),
                    $request->get('hasta')
                ]);
            }

            $pagos = $query->orderBy('fecha_pago', 'desc')->get();

            return response()->json([
                'message' => 'Pagos obtenidos exitosamente',
                'pagos' => $pagos,
                'total' => $pagos->count()
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener pagos'
            ], 500);
        }
    }

    /**
     * Obtener un pago específico
     * GET /payments/{id}
     */
    public function show($pagoId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $pago = Pago::with(['pedido', 'metodos_pago'])
                        ->whereHas('pedido', function ($q) use ($user) {
                            $q->where('usuarios_id_usuario', $user->id_usuario);
                        })
                        ->find($pagoId);

            if (!$pago) {
                return response()->json([
                    'error' => 'Pago no encontrado'
                ], 404);
            }

            return response()->json([
                'message' => 'Pago obtenido exitosamente',
                'pago' => $pago
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener el pago'
            ], 500);
        }
    }

    /**
     * Actualizar el estado de un pago
     * PATCH /payments/{id}/status
     */
    public function updateStatus(Request $request, $pagoId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'estado_pago' => 'required|string|in:pendiente,completado,fallido,cancelado',
                'referencia_pago' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $pago = Pago::with('pedido')->find($pagoId);

            if (!$pago) {
                return response()->json([
                    'error' => 'Pago no encontrado'
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Actualizar el pago
                $updateData = [
                    'estado_pago' => $request->estado_pago
                ];

                if ($request->has('referencia_pago')) {
                    $updateData['referencia_pago'] = $request->referencia_pago;
                }

                $pago->update($updateData);

                // Actualizar el estado del pedido según el estado del pago
                if ($request->estado_pago === 'completado') {
                    $pago->pedido->update(['estado' => 'pagado']);
                } elseif ($request->estado_pago === 'fallido' || $request->estado_pago === 'cancelado') {
                    $pago->pedido->update(['estado' => 'cancelado']);
                }

                DB::commit();

                $pago->load(['pedido', 'metodos_pago']);

                return response()->json([
                    'message' => 'Estado del pago actualizado exitosamente',
                    'pago' => $pago
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error al actualizar estado del pago: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el estado del pago'
            ], 500);
        }
    }

    /**
     * Confirmar pago desde webhook de Mercado Pago
     * POST /payments/confirm
     */
    public function confirmPayment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_id' => 'required|string',
                'status' => 'required|string',
                'external_reference' => 'required|string',
                'merchant_order_id' => 'nullable|string',
                'preference_id' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Extraer el ID del pedido de la referencia externa
            $orderId = intval($request->external_reference);
            
            if (!$orderId) {
                return response()->json([
                    'error' => 'Referencia externa inválida'
                ], 400);
            }

            // Buscar el pedido
            $pedido = Pedido::find($orderId);
            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado'
                ], 404);
            }

            // Buscar el pago existente para este pedido (incluyendo abandonados para pedidos reanudados)
            $pago = Pago::where('pedidos_id_pedido', $orderId)
                       ->whereIn('estado_pago', ['pendiente', 'abandonado'])
                       ->first();

            if (!$pago) {
                return response()->json([
                    'error' => 'Pago pendiente o abandonado no encontrado'
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Actualizar el estado del pago según la respuesta de Mercado Pago
                $estadoPago = match($request->status) {
                    'approved' => 'completado',
                    'pending' => 'pendiente',
                    'rejected', 'cancelled' => 'fallido',
                    default => 'pendiente'
                };

                // Generar referencia_pago en el formato correcto
                $referencePago = 'ORDER_' . $orderId . '_' . $request->payment_id;

                $pago->update([
                    'estado_pago' => $estadoPago,
                    'referencia_pago' => $referencePago,
                    'fecha_pago' => now()
                ]);                // Actualizar el estado del pedido si el pago fue aprobado
                if ($estadoPago === 'completado') {
                    $pedido->update([
                        'estado' => 'confirmado'
                    ]);

                    // Crear registro de envío automáticamente
                    $this->createShippingRecord($pedido);
                    
                } elseif ($estadoPago === 'fallido') {
                    $pedido->update([
                        'estado' => 'cancelado'
                    ]);
                }

                DB::commit();

                Log::info('Pago confirmado desde Mercado Pago', [
                    'payment_id' => $request->payment_id,
                    'pedido_id' => $orderId,
                    'estado_pago' => $estadoPago,
                    'estado_pedido' => $pedido->estado
                ]);

                return response()->json([
                    'message' => 'Pago procesado exitosamente',
                    'payment_status' => $estadoPago,
                    'order_status' => $pedido->estado
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error al confirmar pago: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al procesar la confirmación del pago'
            ], 500);
        }
    }

    /**
     * Obtener información de pago por pedido
     * GET /orders/{order_id}/payment
     */
    public function getPaymentByOrder($orderId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            // Verificar que el pedido pertenece al usuario
            $pedido = Pedido::where('id_pedido', $orderId)
                           ->where('usuarios_id_usuario', $user->id_usuario)
                           ->first();

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado o no autorizado'
                ], 404);
            }

            // Obtener los pagos del pedido
            $pagos = Pago::with('metodos_pago')
                        ->where('pedidos_id_pedido', $orderId)
                        ->orderBy('fecha_pago', 'desc')
                        ->get();

            return response()->json([
                'message' => 'Información de pagos obtenida exitosamente',
                'order_id' => $orderId,
                'payments' => $pagos,
                'total_payments' => $pagos->count()
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener información de pagos'
            ], 500);
        }
    }

    // MÉTODOS PARA ADMINISTRADORES

    /**
     * Obtener todos los pagos (Admin)
     * GET /admin/payments
     */
    public function getAllPayments(Request $request)
    {
        try {
            $query = Pago::with(['pedido.usuario', 'metodos_pago']);

            // Filtros opcionales
            if ($request->has('estado')) {
                $query->where('estado_pago', $request->get('estado'));
            }

            if ($request->has('metodo_pago')) {
                $query->where('metodos_pago_id_metodo_pago', $request->get('metodo_pago'));
            }

            if ($request->has('desde') && $request->has('hasta')) {
                $query->whereBetween('fecha_pago', [
                    $request->get('desde'),
                    $request->get('hasta')
                ]);
            }

            $pagos = $query->orderBy('fecha_pago', 'desc')->get();

            return response()->json([
                'message' => 'Pagos obtenidos exitosamente',
                'pagos' => $pagos,
                'total' => $pagos->count(),
                'statistics' => [
                    'total_amount' => $pagos->where('estado_pago', 'completado')->sum('monto_pago'),
                    'pending_payments' => $pagos->where('estado_pago', 'pendiente')->count(),
                    'completed_payments' => $pagos->where('estado_pago', 'completado')->count(),
                    'failed_payments' => $pagos->where('estado_pago', 'fallido')->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener pagos'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de pagos (Admin)
     * GET /admin/payments/statistics
     */
    public function getPaymentStatistics()
    {
        try {
            $stats = [
                'overview' => [
                    'total_payments' => Pago::count(),
                    'total_amount' => Pago::where('estado_pago', 'completado')->sum('monto_pago'),
                    'pending_amount' => Pago::where('estado_pago', 'pendiente')->sum('monto_pago'),
                    'failed_payments' => Pago::where('estado_pago', 'fallido')->count()
                ],
                'by_status' => Pago::selectRaw('estado_pago, count(*) as count, sum(monto_pago) as total_amount')
                    ->groupBy('estado_pago')
                    ->get(),
                'by_payment_method' => Pago::with('metodos_pago')
                    ->selectRaw('metodos_pago_id_metodo_pago, count(*) as count, sum(monto_pago) as total_amount')
                    ->groupBy('metodos_pago_id_metodo_pago')
                    ->get(),
                'recent_payments' => Pago::with(['pedido.usuario', 'metodos_pago'])
                    ->orderBy('fecha_pago', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'message' => 'Estadísticas de pagos obtenidas exitosamente',
                'statistics' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener estadísticas de pagos'
            ], 500);
        }
    }    /**
     * Crear registro de envío automáticamente cuando el pago es confirmado
     * @param Pedido $pedido
     * @return void
     */
    private function createShippingRecord(Pedido $pedido)
    {
        try {
            // Verificar si ya existe un envío para este pedido (creado durante el checkout)
            $existingShipping = Envio::where('pedidos_id_pedido', $pedido->id_pedido)->first();
            
            if ($existingShipping) {
                Log::info('Ya existe un envío para el pedido - manteniendo costo original', [
                    'pedido_id' => $pedido->id_pedido,
                    'envio_id' => $existingShipping->id_envio,
                    'monto_envio_existente' => $existingShipping->monto_envio
                ]);
                  // Solo actualizar el estado si es necesario, pero mantener el monto original
                if ($existingShipping->estado === 'pendiente') {
                    $existingShipping->update(['estado' => 'confirmado']);
                    Log::info('Estado de envío actualizado a confirmado', [
                        'envio_id' => $existingShipping->id_envio
                    ]);
                }

                // Verificar si hay una dirección que necesita ser asignada a este envío
                $this->assignAddressToShipping($existingShipping, $pedido);
                return;
            }

            // Si no existe envío, calcular costo usando la lógica del frontend (fallback)
            $costoEnvio = $this->calculateShippingCostFromOrder($pedido);
            
            // Asignar transportista (rotación simple)
            $transportistaId = $this->assignTransporter();            // Obtener la dirección predeterminada del usuario para asignar al envío
            $defaultAddress = Direccion::where('usuarios_id_usuario', $pedido->usuarios_id_usuario)
                                     ->where('predeterminada', 'si')
                                     ->first();
            
            // Si no hay dirección predeterminada, usar cualquier dirección del usuario
            if (!$defaultAddress) {
                $defaultAddress = Direccion::where('usuarios_id_usuario', $pedido->usuarios_id_usuario)
                                         ->first();
            }

            // Crear el registro de envío con estado "confirmado" ya que el pago fue aprobado
            // 🔧 Obtener datos para snapshots
            $transportista = DB::table('transportistas')->where('id_transportista', $transportistaId)->first();
            
            // Crear snapshots
            $addressSnapshot = $this->createAddressSnapshot($defaultAddress);
            $transportistSnapshot = $this->createTransportistSnapshot($transportista);
            
            $envio = Envio::create(array_merge([
                'monto_envio' => $costoEnvio,
                'estado' => 'confirmado', // Estado confirmado ya que el pago fue aprobado
                'fecha_envio' => now(), // 🔧 Mismo día para fresas frescas (entrega en 1-2 horas)
                'transportistas_id_transportista' => $transportistaId,
                'pedidos_id_pedido' => $pedido->id_pedido,
                'direcciones_id_direccion' => $defaultAddress ? $defaultAddress->id_direccion : null
            ], $addressSnapshot, $transportistSnapshot));

            Log::info('Envío creado automáticamente desde confirmación de pago (fallback) con snapshots', [
                'pedido_id' => $pedido->id_pedido,
                'envio_id' => $envio->id_envio,
                'transportista_id' => $transportistaId,
                'transportista_nombre' => $transportistSnapshot['transportista_nombre_snapshot'],
                'monto_envio' => $costoEnvio,
                'estado' => 'confirmado',
                'direccion_id' => $defaultAddress ? $defaultAddress->id_direccion : null,
                'direccion_snapshot' => $addressSnapshot['direccion_linea1_snapshot'],
                'direccion_asignada' => $defaultAddress ? 'si' : 'no'
            ]);

            // Ya no necesitamos llamar assignAddressToShipping porque la dirección ya está asignada directamente

        } catch (\Exception $e) {
            Log::error('Error al crear registro de envío automático', [
                'pedido_id' => $pedido->id_pedido,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzamos la excepción para que no falle el proceso de confirmación de pago
        }
    }

    /**
     * Calcular costo de envío basado en los items del pedido (lógica del frontend)
     * @param Pedido $pedido
     * @return float
     */
    private function calculateShippingCostFromOrder(Pedido $pedido)
    {
        try {
            // Obtener los items del pedido con sus productos
            $pedidoItems = $pedido->pedido_items()->with('producto')->get();
            
            // Calcular subtotal de paquetes de fresas y total del pedido
            $strawberryPacksSubtotal = 0;
            $orderTotal = 0;
            
            foreach ($pedidoItems as $item) {
                $orderTotal += $item->subtotal;
                
                // Verificar si el producto pertenece a la categoría de paquetes de fresas
                if ($item->producto && $item->producto->categorias_id_categoria == 1) {
                    $strawberryPacksSubtotal += $item->subtotal;
                }
            }
            
            // 🔧 NUEVA LÓGICA: Envío gratis si:
            // 1. Total del pedido >= S/ 30 (cualquier producto) O
            // 2. Subtotal de fresas >= S/ 30 (condición original)
            $FREE_SHIPPING_THRESHOLD = 30.00;
            $hasCartTotalOffer = $orderTotal >= $FREE_SHIPPING_THRESHOLD;
            $hasStrawberryPackOffer = $strawberryPacksSubtotal >= $FREE_SHIPPING_THRESHOLD;
            
            return ($hasCartTotalOffer || $hasStrawberryPackOffer) ? 0.00 : 5.00;
            
        } catch (\Exception $e) {
            Log::error('Error calculando costo de envío', [
                'pedido_id' => $pedido->id_pedido,
                'error' => $e->getMessage()
            ]);
            
            // Fallback: usar la lógica anterior si hay error
            return $pedido->monto_total >= 30.00 ? 0.00 : 5.00;
        }
    }    /**
     * Asignar transportista (rotación simple)
     * @return int
     */
    private function assignTransporter()
    {
        // Obtener todos los transportistas disponibles
        $transportistas = DB::table('transportistas')->pluck('id_transportista')->toArray();
        
        if (empty($transportistas)) {
            // Si no hay transportistas, usar ID 1 por defecto
            return 1;
        }
        
        // Obtener el último envío para ver cuál transportista fue asignado
        $ultimoEnvio = Envio::latest('id_envio')->first();
        
        if (!$ultimoEnvio) {
            // Si es el primer envío, usar el primer transportista
            return $transportistas[0];
        }
        
        // Encontrar el índice del último transportista usado
        $ultimoTransportistaIndex = array_search($ultimoEnvio->transportistas_id_transportista, $transportistas);
        
        // Asignar el siguiente transportista (rotación)
        $siguienteIndex = ($ultimoTransportistaIndex + 1) % count($transportistas);        return $transportistas[$siguienteIndex];
    }

    /**
     * Asignar una dirección al envío si aún no tiene una asignada
     * @param Envio $envio
     * @param Pedido $pedido
     * @return void
     */    private function assignAddressToShipping(Envio $envio, Pedido $pedido)
    {
        try {
            // Verificar si ya hay una dirección asignada a este envío (nueva relación)
            if ($envio->direcciones_id_direccion) {
                Log::info('Ya hay una dirección asignada a este envío', [
                    'envio_id' => $envio->id_envio,
                    'direccion_id' => $envio->direcciones_id_direccion
                ]);
                return;
            }

            // Buscar la dirección predeterminada del usuario del pedido
            $defaultAddress = Direccion::where('usuarios_id_usuario', $pedido->usuarios_id_usuario)
                                     ->where('predeterminada', 'si')
                                     ->first();

            if ($defaultAddress) {
                $envio->update(['direcciones_id_direccion' => $defaultAddress->id_direccion]);
                Log::info('Dirección predeterminada asignada al envío durante confirmación de pago', [
                    'envio_id' => $envio->id_envio,
                    'direccion_id' => $defaultAddress->id_direccion,
                    'direccion' => $defaultAddress->calle . ' ' . $defaultAddress->numero . ', ' . $defaultAddress->distrito
                ]);
            } else {
                // Si no hay dirección predeterminada, buscar cualquier dirección disponible del usuario
                $anyAddress = Direccion::where('usuarios_id_usuario', $pedido->usuarios_id_usuario)
                                     ->first();
                
                if ($anyAddress) {
                    $envio->update(['direcciones_id_direccion' => $anyAddress->id_direccion]);
                    Log::info('Dirección disponible asignada al envío durante confirmación de pago', [
                        'envio_id' => $envio->id_envio,
                        'direccion_id' => $anyAddress->id_direccion,
                        'direccion' => $anyAddress->calle . ' ' . $anyAddress->numero . ', ' . $anyAddress->distrito
                    ]);
                } else {
                    Log::warning('No se encontró ninguna dirección disponible para asignar al envío', [
                        'envio_id' => $envio->id_envio,
                        'usuario_id' => $pedido->usuarios_id_usuario,
                        'pedido_id' => $pedido->id_pedido
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error al asignar dirección al envío', [
                'envio_id' => $envio->id_envio,
                'pedido_id' => $pedido->id_pedido,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Actualizar estados después de regresar de Mercado Pago
     * POST /payments/update-status-from-mp
     */
    public function updateStatusFromMercadoPago(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'order_id' => 'required|integer|exists:pedidos,id_pedido',
                'payment_status' => 'required|string|in:approved,pending,rejected,cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $orderId = $request->order_id;
            $paymentStatus = $request->payment_status;

            // Buscar el pedido
            $pedido = Pedido::find($orderId);
            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado'
                ], 404);
            }

            // Buscar el pago pendiente o abandonado para este pedido (para pedidos reanudados)
            $pago = Pago::where('pedidos_id_pedido', $orderId)
                       ->whereIn('estado_pago', ['pendiente', 'abandonado'])
                       ->first();

            if (!$pago) {
                return response()->json([
                    'error' => 'Pago pendiente o abandonado no encontrado'
                ], 404);
            }

            // Buscar el envío para este pedido
            $envio = Envio::where('pedidos_id_pedido', $orderId)->first();

            DB::beginTransaction();

            try {
                // Actualizar el estado del pago según la respuesta de Mercado Pago
                $estadoPago = match($paymentStatus) {
                    'approved' => 'completado',
                    'pending' => 'pendiente',
                    'rejected', 'cancelled' => 'fallido',
                    default => 'pendiente'
                };

                // Generar referencia_pago en el formato correcto
                $referencePago = 'ORDER_' . $orderId . '_' . time();

                $pago->update([
                    'estado_pago' => $estadoPago,
                    'referencia_pago' => $referencePago,
                    'fecha_pago' => now()
                ]);

                // Actualizar el estado del pedido y envío
                if ($estadoPago === 'completado') {
                    $pedido->update(['estado' => 'confirmado']);
                    
                    // Actualizar estado del envío si existe
                    if ($envio) {
                        $envio->update(['estado' => 'confirmado']);
                    }
                    
                } elseif ($estadoPago === 'fallido') {
                    $pedido->update(['estado' => 'cancelado']);
                    
                    // Cancelar envío si existe
                    if ($envio) {
                        $envio->update(['estado' => 'cancelado']);
                    }
                }

                DB::commit();

                Log::info('Estados actualizados desde frontend después de Mercado Pago', [
                    'pedido_id' => $orderId,
                    'estado_pago' => $estadoPago,
                    'estado_pedido' => $pedido->estado,
                    'estado_envio' => $envio ? $envio->estado : 'No encontrado'
                ]);

                return response()->json([
                    'message' => 'Estados actualizados exitosamente',
                    'payment_status' => $estadoPago,
                    'order_status' => $pedido->estado,
                    'shipping_status' => $envio ? $envio->estado : null
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error al actualizar estados desde frontend: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar los estados'
            ], 500);
        }
    }

    /**
     * Confirmar pago exitoso al regresar de Mercado Pago
     * POST /payments/success
     */
    public function handlePaymentSuccess(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'order_id' => 'required|integer',
                'payment_id' => 'nullable|string',
                'status' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $orderId = $request->order_id;

            // Buscar el pedido que pertenece al usuario autenticado
            $pedido = Pedido::where('id_pedido', $orderId)
                          ->where('usuarios_id_usuario', $user->id_usuario)
                          ->first();

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado o no autorizado'
                ], 404);
            }

            // Buscar el pago asociado al pedido
            $pago = Pago::where('pedidos_id_pedido', $orderId)->first();

            if (!$pago) {
                return response()->json([
                    'error' => 'Pago no encontrado para este pedido'
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Log del estado inicial
                Log::info('handlePaymentSuccess iniciado', [
                    'pedido_id' => $orderId,
                    'payment_id' => $request->payment_id,
                    'user_id' => $user->id_usuario,
                    'pedido_estado_inicial' => $pedido->estado,
                    'pago_estado_inicial' => $pago->estado_pago,
                    'request_data' => $request->all()
                ]);

                // Actualizar si el pago está pendiente o abandonado (para pedidos reanudados)
                if (in_array($pago->estado_pago, ['pendiente', 'abandonado'])) {
                    $estadoPagoAnterior = $pago->estado_pago;
                    
                    // Generar referencia_pago en el formato correcto
                    $referencePago = $request->payment_id ? 
                        'ORDER_' . $orderId . '_' . $request->payment_id : 
                        'ORDER_' . $orderId . '_' . time();

                    // Actualizar el estado del pago a completado
                    $pagoUpdated = $pago->update([
                        'estado_pago' => 'completado',
                        'referencia_pago' => $referencePago,
                        'fecha_pago' => now()
                    ]);

                    // Actualizar el estado del pedido a confirmado (tanto para pedidos normales como reanudados)
                    $pedidoUpdated = $pedido->update(['estado' => 'confirmado']);

                    // Verificar que las actualizaciones se aplicaron
                    $pagoFresh = $pago->fresh();
                    $pedidoFresh = $pedido->fresh();

                    Log::info('Pago confirmado exitosamente desde la página de éxito', [
                        'pedido_id' => $orderId,
                        'payment_id' => $request->payment_id,
                        'user_id' => $user->id_usuario,
                        'referencia_pago' => $referencePago,
                        'was_resumed' => $estadoPagoAnterior === 'abandonado',
                        'estado_pago_anterior' => $estadoPagoAnterior,
                        'pedido_estado_nuevo' => 'confirmado',
                        'pago_estado_nuevo' => 'completado',
                        'pago_update_result' => $pagoUpdated,
                        'pedido_update_result' => $pedidoUpdated,
                        'pago_fresh_estado' => $pagoFresh ? $pagoFresh->estado_pago : 'null',
                        'pedido_fresh_estado' => $pedidoFresh ? $pedidoFresh->estado : 'null'
                    ]);
                } else {
                    Log::warning('Pago ya procesado anteriormente o estado no válido', [
                        'pedido_id' => $orderId,
                        'current_pago_status' => $pago->estado_pago,
                        'current_pedido_status' => $pedido->estado,
                        'user_id' => $user->id_usuario
                    ]);
                }

                DB::commit();

                return response()->json([
                    'message' => 'Pago confirmado exitosamente',
                    'order_id' => $orderId,
                    'payment_status' => $pago->fresh()->estado_pago,
                    'order_status' => $pedido->fresh()->estado,
                    'referencia_pago' => $pago->fresh()->referencia_pago
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Error al confirmar pago desde página de éxito: ' . $e->getMessage(), [
                'request_data' => $request->all(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Error al confirmar el pago'
            ], 500);
        }
    }
}

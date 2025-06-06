<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\MetodosPago;
use App\Models\Envio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PagosController extends Controller
{
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
                // Crear el pago
                $pago = Pago::create([
                    'fecha_pago' => now(),
                    'monto_pago' => $request->monto_pago,
                    'estado_pago' => $request->get('estado_pago', 'pendiente'),
                    'referencia_pago' => $request->referencia_pago,
                    'pedidos_id_pedido' => $request->pedido_id,
                    'metodos_pago_id_metodo_pago' => $request->metodo_pago_id
                ]);

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

            // Buscar el pago existente para este pedido
            $pago = Pago::where('pedidos_id_pedido', $orderId)
                       ->where('estado_pago', 'pendiente')
                       ->first();

            if (!$pago) {
                return response()->json([
                    'error' => 'Pago pendiente no encontrado'
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

                $pago->update([
                    'estado_pago' => $estadoPago,
                    'referencia_pago' => 'MP_' . $request->payment_id,
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
    }

    /**
     * Crear registro de envío automáticamente cuando el pago es confirmado
     * @param Pedido $pedido
     * @return void
     */
    private function createShippingRecord(Pedido $pedido)
    {
        try {
            // Verificar si ya existe un envío para este pedido
            $existingShipping = Envio::where('pedidos_id_pedido', $pedido->id_pedido)->first();
            
            if ($existingShipping) {
                Log::info('Ya existe un envío para el pedido', [
                    'pedido_id' => $pedido->id_pedido,
                    'envio_id' => $existingShipping->id_envio
                ]);
                return;
            }

            // Calcular costo de envío (puede ser 0 si hay promociones)
            $costoEnvio = $this->calculateShippingCost($pedido);
            
            // Asignar transportista (rotación simple - puede mejorarse)
            $transportistaId = $this->assignTransporter();

            // Crear el registro de envío
            $envio = Envio::create([
                'monto_envio' => $costoEnvio,
                'estado' => 'programado',
                'fecha_envio' => date('Y-m-d H:i:s', strtotime('+1 day')), // Envío programado para el día siguiente
                'transportistas_id_transportista' => $transportistaId,
                'pedidos_id_pedido' => $pedido->id_pedido
            ]);

            Log::info('Envío creado automáticamente', [
                'pedido_id' => $pedido->id_pedido,
                'envio_id' => $envio->id_envio,
                'transportista_id' => $transportistaId,
                'monto_envio' => $costoEnvio,
                'estado' => 'programado'
            ]);

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
     * Calcular costo de envío
     * @param Pedido $pedido
     * @return float
     */
    private function calculateShippingCost(Pedido $pedido)
    {
        // Lógica básica: envío gratis para pedidos >= S/ 30
        // Esto puede ser más complejo según las reglas de negocio
        
        if ($pedido->monto_total >= 30.00) {
            return 0.00; // Envío gratis
        }
        
        return 10.00; // Costo fijo de envío
    }

    /**
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
        $siguienteIndex = ($ultimoTransportistaIndex + 1) % count($transportistas);
        
        return $transportistas[$siguienteIndex];
    }
}

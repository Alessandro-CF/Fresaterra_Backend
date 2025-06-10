<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\PedidoItems;
use App\Models\Producto;
use App\Models\Direccion;
use App\Models\Pago;
use App\Models\MetodosPago;
use App\Models\Envio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PedidoController extends Controller
{
    /**
     * Obtener todos los pedidos del usuario autenticado
     * GET /me/orders
     */    public function index()
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }            $pedidos = Pedido::with(['pedido_items.producto', 'pagos.metodos_pago', 'envios.direccion', 'envios.transportista'])
                            ->where('usuarios_id_usuario', $user->id_usuario)
                            ->orderBy('fecha_creacion', 'desc')
                            ->get();

            return response()->json([
                'message' => 'Pedidos obtenidos exitosamente',
                'orders' => $pedidos,
                'total' => $pedidos->count()
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Error al obtener pedidos: ' . $e->getMessage(), [
                'user_id' => isset($user) ? $user->id_usuario : null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al obtener pedidos'
            ], 500);
        }
    }

    /**
     * Crear un nuevo pedido desde el checkout
     * POST /orders
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
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|integer|exists:productos,id_producto',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                'monto_total' => 'required|numeric|min:0.01',
                'shipping_info' => 'required|array',
                'shipping_info.firstName' => 'required|string|max:100',
                'shipping_info.lastName' => 'required|string|max:100',
                'shipping_info.email' => 'required|email|max:100',
                'shipping_info.phone' => 'required|string|max:15',
                'address_info' => 'required|array',
                'address_info.type' => 'required|string|in:profile,select,new',
                'address_info.address_id' => 'required_if:address_info.type,profile,select|nullable|integer',
                'address_info.new_address' => 'required_if:address_info.type,new|nullable|array',
                'address_info.new_address.calle' => 'required_if:address_info.type,new|string|max:100',
                'address_info.new_address.numero' => 'required_if:address_info.type,new|string|max:10',
                'address_info.new_address.distrito' => 'required_if:address_info.type,new|string|max:50',
                'address_info.new_address.ciudad' => 'required_if:address_info.type,new|string|max:50',
                'address_info.new_address.referencia' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }            // Validar que todos los productos existen y están disponibles
            $productIds = collect($request->items)->pluck('product_id');
            $productos = Producto::whereIn('id_producto', $productIds)
                               ->where('estado', 'activo') // Solo productos activos
                               ->get();

            if ($productos->count() !== $productIds->count()) {
                return response()->json([
                    'error' => 'Uno o más productos no están disponibles'
                ], 400);
            }

            // Validar dirección si es necesario
            $addressId = null;
            if ($request->address_info['type'] === 'profile' || $request->address_info['type'] === 'select') {
                $address = $user->direcciones()->find($request->address_info['address_id']);
                if (!$address) {
                    return response()->json([
                        'error' => 'Dirección no encontrada'
                    ], 404);
                }
                $addressId = $address->id_direccion;
            } elseif ($request->address_info['type'] === 'new') {
                // Crear nueva dirección si es necesario
                $newAddressData = $request->address_info['new_address'];
                $address = Direccion::create([
                    'calle' => $newAddressData['calle'],
                    'numero' => $newAddressData['numero'],
                    'distrito' => $newAddressData['distrito'],
                    'ciudad' => $newAddressData['ciudad'],
                    'referencia' => $newAddressData['referencia'] ?? null,
                    'predeterminada' => 'no',
                    'usuarios_id_usuario' => $user->id_usuario
                ]);
                $addressId = $address->id_direccion;
            }

            DB::beginTransaction();

            try {
                // Crear el pedido
                $pedido = Pedido::create([
                    'monto_total' => $request->monto_total,
                    'estado' => 'pendiente',
                    'fecha_creacion' => now(),
                    'usuarios_id_usuario' => $user->id_usuario
                ]);                // Crear los items del pedido
                foreach ($request->items as $item) {
                    $producto = $productos->find($item['product_id']);
                    
                    PedidoItems::create([
                        'cantidad' => $item['quantity'],
                        'precio' => $item['price'],
                        'subtotal' => $item['quantity'] * $item['price'],
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'productos_id_producto' => $item['product_id']
                    ]);
                }

                // Crear registro de pago inicial con estado pendiente
                // Por defecto usamos Mercado Pago (ID 1) ya que es hacia donde vamos a redirigir
                $metodoPago = MetodosPago::where('nombre', 'Mercado Pago')->first();
                if (!$metodoPago) {
                    $metodoPago = MetodosPago::where('activo', true)->first(); // Fallback al primer método activo
                }                if ($metodoPago) {
                    $pago = Pago::create([
                        'fecha_pago' => now(),
                        'monto_pago' => $request->monto_total,
                        'estado_pago' => 'pendiente',
                        'referencia_pago' => 'ORDER_' . $pedido->id_pedido . '_' . time(),
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'metodos_pago_id_metodo_pago' => $metodoPago->id_metodo_pago
                    ]);
                      Log::info('Pago inicial creado', [
                        'pago_id' => $pago->id_pago,
                        'pedido_id' => $pedido->id_pedido,
                        'metodo_pago' => $metodoPago->nombre,
                        'referencia' => $pago->referencia_pago
                    ]);                    // Crear registro de envío inmediatamente con información de dirección
                    $shippingCostFromFrontend = $request->has('shipping_cost') ? $request->shipping_cost : null;
                    $this->createShippingRecord($pedido, $shippingCostFromFrontend, $addressId);
                }

                DB::commit();

                // Cargar relaciones para la respuesta
                $pedido->load(['pedido_items.producto', 'usuario']);

                Log::info('Pedido creado exitosamente', [
                    'pedido_id' => $pedido->id_pedido,
                    'usuario_id' => $user->id_usuario,
                    'monto_total' => $request->monto_total,
                    'items_count' => count($request->items)
                ]);

                return response()->json([
                    'message' => 'Pedido creado exitosamente',
                    'order' => $pedido,
                    'order_id' => $pedido->id_pedido
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
            Log::error('Error al crear pedido: ' . $e->getMessage(), [
                'user_id' => $user->id_usuario ?? null,
                'request_data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Error al crear el pedido'
            ], 500);
        }
    }

    /**
     * Obtener un pedido específico
     * GET /orders/{id}
     */
    public function show($pedidoId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }            $pedido = Pedido::with(['pedido_items.producto', 'pagos.metodos_pago', 'envios.direccion', 'envios.transportista', 'usuario'])
                           ->where('id_pedido', $pedidoId)
                           ->where('usuarios_id_usuario', $user->id_usuario)
                           ->first();

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado'
                ], 404);
            }

            return response()->json([
                'message' => 'Pedido obtenido exitosamente',
                'order' => $pedido
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener el pedido'
            ], 500);
        }
    }

    /**
     * Actualizar el estado de un pedido
     * PATCH /orders/{id}/status
     */
    public function updateStatus(Request $request, $pedidoId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'estado' => 'required|string|in:pendiente,confirmado,en_preparacion,enviado,entregado,cancelado'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $pedido = Pedido::find($pedidoId);

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado'
                ], 404);
            }

            $pedido->update(['estado' => $request->estado]);

            return response()->json([
                'message' => 'Estado del pedido actualizado exitosamente',
                'order' => $pedido
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar el estado del pedido'
            ], 500);
        }
    }

    /**
     * Cancelar un pedido
     * PATCH /orders/{id}/cancel
     */
    public function cancel($pedidoId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $pedido = Pedido::where('id_pedido', $pedidoId)
                           ->where('usuarios_id_usuario', $user->id_usuario)
                           ->first();

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado'
                ], 404);
            }

            // Solo se pueden cancelar pedidos pendientes o confirmados
            if (!in_array($pedido->estado, ['pendiente', 'confirmado'])) {
                return response()->json([
                    'error' => 'No se puede cancelar un pedido en estado: ' . $pedido->estado
                ], 400);
            }

            $pedido->update(['estado' => 'cancelado']);

            return response()->json([
                'message' => 'Pedido cancelado exitosamente',
                'order' => $pedido
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al cancelar el pedido'
            ], 500);
        }
    }

    // MÉTODOS PARA ADMINISTRADORES

    /**
     * Obtener todos los pedidos (Admin)
     * GET /admin/orders
     */
    public function getAllOrders(Request $request)
    {
        try {
            $query = Pedido::with(['usuario', 'pedido_items.producto', 'pagos.metodos_pago', 'envios.direccion', 'envios.transportista']);

            // Filtros opcionales
            if ($request->has('estado')) {
                $query->where('estado', $request->get('estado'));
            }

            if ($request->has('usuario_id')) {
                $query->where('usuarios_id_usuario', $request->get('usuario_id'));
            }

            if ($request->has('desde') && $request->has('hasta')) {
                $query->whereBetween('fecha_creacion', [
                    $request->get('desde'),
                    $request->get('hasta')
                ]);
            }

            $pedidos = $query->orderBy('fecha_creacion', 'desc')->get();

            return response()->json([
                'message' => 'Pedidos obtenidos exitosamente',
                'orders' => $pedidos,
                'total' => $pedidos->count(),
                'statistics' => [
                    'total_amount' => $pedidos->sum('monto_total'),
                    'pending_orders' => $pedidos->where('estado', 'pendiente')->count(),
                    'completed_orders' => $pedidos->where('estado', 'entregado')->count(),
                    'cancelled_orders' => $pedidos->where('estado', 'cancelado')->count()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener pedidos'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de pedidos (Admin)
     * GET /admin/orders/statistics
     */
    public function getOrderStatistics()
    {
        try {
            $stats = [
                'overview' => [
                    'total_orders' => Pedido::count(),
                    'total_revenue' => Pedido::whereIn('estado', ['entregado', 'pagado'])->sum('monto_total'),
                    'pending_orders' => Pedido::where('estado', 'pendiente')->count(),
                    'average_order_value' => round(Pedido::avg('monto_total'), 2)
                ],
                'by_status' => Pedido::selectRaw('estado, count(*) as count, sum(monto_total) as total_amount')
                    ->groupBy('estado')
                    ->get(),
                'monthly_sales' => Pedido::selectRaw('YEAR(fecha_creacion) as year, MONTH(fecha_creacion) as month, count(*) as orders_count, sum(monto_total) as total_sales')
                    ->whereIn('estado', ['entregado', 'pagado'])
                    ->groupByRaw('YEAR(fecha_creacion), MONTH(fecha_creacion)')
                    ->orderByRaw('YEAR(fecha_creacion) DESC, MONTH(fecha_creacion) DESC')
                    ->limit(12)
                    ->get(),
                'recent_orders' => Pedido::with(['usuario', 'pedido_items.producto'])
                    ->orderBy('fecha_creacion', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'message' => 'Estadísticas de pedidos obtenidas exitosamente',
                'statistics' => $stats
            ], 200);        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener estadísticas de pedidos'
            ], 500);
        }
    }    /**
     * Crear registro de envío automáticamente al crear el pedido
     * @param Pedido $pedido
     * @param float|null $shippingCostFromFrontend Costo de envío calculado en el frontend
     * @param int|null $addressId ID de la dirección de envío
     * @return void
     */
    private function createShippingRecord(Pedido $pedido, $shippingCostFromFrontend = null, $addressId = null)
    {
        try {
            // Usar el costo de envío del frontend si está disponible, sino calcular
            $costoEnvio = $shippingCostFromFrontend !== null 
                ? $shippingCostFromFrontend 
                : $this->calculateShippingCostFromOrder($pedido);
            
            // Asignar transportista (rotación simple)
            $transportistaId = $this->assignTransporter();            // Crear el registro de envío con estado "pendiente"
            $envio = Envio::create([
                'monto_envio' => $costoEnvio,
                'estado' => 'pendiente', // Estado pendiente hasta que se confirme el pago
                'fecha_envio' => now()->addDay(), // Programado para el día siguiente
                'transportistas_id_transportista' => $transportistaId,
                'pedidos_id_pedido' => $pedido->id_pedido,
                'direcciones_id_direccion' => $addressId // NUEVA RELACIÓN: FK en envios hacia direcciones
            ]);            Log::info('Envío creado al momento del checkout', [
                'pedido_id' => $pedido->id_pedido,
                'envio_id' => $envio->id_envio,
                'transportista_id' => $transportistaId,
                'monto_envio' => $costoEnvio,
                'estado' => 'pendiente',
                'direccion_id' => $addressId,
                'shipping_from_frontend' => $shippingCostFromFrontend !== null
            ]);

            // Logging adicional para verificar la asignación de dirección
            if ($addressId) {
                Log::info('Dirección asignada al envío exitosamente', [
                    'envio_id' => $envio->id_envio,
                    'direccion_id' => $addressId
                ]);
            } else {
                Log::warning('No se proporcionó addressId para asignar al envío', [
                    'envio_id' => $envio->id_envio
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error al crear registro de envío en checkout', [
                'pedido_id' => $pedido->id_pedido,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzamos la excepción para que no falle el proceso de creación del pedido
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
            
            // Calcular subtotal de paquetes de fresas (categoryId: '1' según el frontend)
            $strawberryPacksSubtotal = 0;
            
            foreach ($pedidoItems as $item) {
                // Verificar si el producto pertenece a la categoría de paquetes de fresas
                if ($item->producto && $item->producto->categorias_id_categoria == 1) {
                    $strawberryPacksSubtotal += $item->subtotal;
                }
            }
            
            // Aplicar la misma lógica del frontend: envío gratis si subtotal de fresas >= S/ 30
            if ($strawberryPacksSubtotal >= 30.00) {
                return 0.00; // Envío gratis
            }
            
            return 5.99; // Costo fijo como en el frontend
            
        } catch (\Exception $e) {
            Log::error('Error calculando costo de envío', [
                'pedido_id' => $pedido->id_pedido,
                'error' => $e->getMessage()
            ]);
            
            // Fallback: usar la lógica anterior si hay error
            return $pedido->monto_total >= 30.00 ? 0.00 : 5.99;
        }
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

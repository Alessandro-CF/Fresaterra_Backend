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
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PedidoController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    // 🔧 MÉTODOS HELPER PARA SNAPSHOTS
    
    /**
     * Crear snapshot de producto para pedido_items
     */
    private function createProductSnapshot(Producto $producto): array
    {
        // Construir URL completa de imagen
        $imagenUrl = null;
        if ($producto->url_imagen) {
            // Si la URL ya es completa (empieza con http), usarla tal como está
            if (str_starts_with($producto->url_imagen, 'http')) {
                $imagenUrl = $producto->url_imagen;
            } else {
                // Si es una ruta relativa, construir URL completa
                $imagenUrl = asset('storage/' . ltrim($producto->url_imagen, '/'));
            }
        }
        
        return [
            'producto_nombre_snapshot' => $producto->nombre,
            'producto_descripcion_snapshot' => $producto->descripcion,
            'producto_imagen_snapshot' => $imagenUrl,
            'producto_peso_snapshot' => $producto->peso,
            'categoria_nombre_snapshot' => $producto->categoria ? $producto->categoria->nombre : null
        ];
    }

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
     * Crear snapshot de método de pago para pagos (optimizado)
     */
    private function createPaymentMethodSnapshot(MetodosPago $metodoPago): array
    {
        return [
            'metodo_pago_nombre_snapshot' => $metodoPago->nombre
        ];
    }

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
            }

            // Si el usuario es admin, mostrar todos los pedidos
            if (isset($user->rol) && ($user->rol === 'admin' || $user->rol === 'superadmin')) {
                $pedidos = Pedido::with(['pedido_items.producto', 'pagos.metodos_pago', 'envios.direccion', 'envios.transportista', 'usuario'])
                    ->orderBy('fecha_creacion', 'desc')
                    ->get();
            } else {
                // Usuario normal: solo sus pedidos
                $pedidos = Pedido::with(['pedido_items.producto', 'pagos.metodos_pago', 'envios.direccion', 'envios.transportista', 'usuario'])
                    ->where('usuarios_id_usuario', $user->id_usuario)
                    ->orderBy('fecha_creacion', 'desc')
                    ->get();
            }

            // Mapear datos completos necesarios para el frontend (incluyendo envíos y pagos)
            $pedidosMapped = $pedidos->map(function($pedido) {
                return [
                    'id_pedido' => $pedido->id_pedido,
                    'codigo_pedido' => $pedido->codigo_pedido,
                    'nombre_usuario' => $pedido->usuario->nombre ?? null,
                    'estado' => $pedido->estado,
                    'monto_total' => $pedido->monto_total,
                    'fecha_creacion' => $pedido->fecha_creacion,
                    // 🔧 AGREGADO: Incluir datos de envío y pago para mostrar estados correctos
                    'envio' => $pedido->envios->first(), // Primer envío
                    'envios' => $pedido->envios, // Todos los envíos (por compatibilidad)
                    'pago' => $pedido->pagos->first(), // Primer pago  
                    'pagos' => $pedido->pagos, // Todos los pagos (por compatibilidad)
                    'detalles' => $pedido->pedido_items, // Items del pedido
                    'pedido_items' => $pedido->pedido_items, // Por compatibilidad
                ];
            });

            // 🔧 DEBUG: Log para verificar los datos que se envían al frontend
            Log::info('Pedidos enviados al frontend', [
                'total_pedidos' => $pedidosMapped->count(),
                'primer_pedido_debug' => $pedidosMapped->first() ? [
                    'id' => $pedidosMapped->first()['id_pedido'],
                    'estado_pedido' => $pedidosMapped->first()['estado'],
                    'tiene_envio' => !empty($pedidosMapped->first()['envio']),
                    'estado_envio' => $pedidosMapped->first()['envio']['estado'] ?? 'sin estado',
                    'tiene_pago' => !empty($pedidosMapped->first()['pago']),
                    'estado_pago' => $pedidosMapped->first()['pago']['estado_pago'] ?? 'sin estado'
                ] : 'no hay pedidos'
            ]);

            return response()->json([
                'message' => 'Pedidos obtenidos exitosamente',
                'orders' => $pedidosMapped,
                'total' => $pedidosMapped->count()
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
                    'estado' => Pedido::ESTADO_PENDIENTE,
                    'fecha_creacion' => now(),
                    'usuarios_id_usuario' => $user->id_usuario
                ]);                // Crear los items del pedido con snapshots
                foreach ($request->items as $item) {
                    $producto = $productos->find($item['product_id']);
                    
                    // 🔧 Crear snapshot del producto para preservar datos históricos
                    $productSnapshot = $this->createProductSnapshot($producto);
                    
                    PedidoItems::create(array_merge([
                        'cantidad' => $item['quantity'],
                        'precio' => $item['price'],
                        'subtotal' => $item['quantity'] * $item['price'],
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'productos_id_producto' => $item['product_id']
                    ], $productSnapshot));
                    
                    Log::info('Item del pedido creado con snapshot', [
                        'pedido_id' => $pedido->id_pedido,
                        'producto_id' => $item['product_id'],
                        'producto_nombre' => $productSnapshot['producto_nombre_snapshot'],
                        'categoria' => $productSnapshot['categoria_nombre_snapshot']
                    ]);
                }

                // Crear registro de pago inicial con estado pendiente
                // Por defecto usamos Mercado Pago (ID 1) ya que es hacia donde vamos a redirigir
                $metodoPago = MetodosPago::where('nombre', 'Mercado Pago')->first();
                if (!$metodoPago) {
                    $metodoPago = MetodosPago::where('activo', true)->first(); // Fallback al primer método activo
                }                if ($metodoPago) {
                    // 🔧 Crear snapshot del método de pago para preservar datos históricos
                    $paymentMethodSnapshot = $this->createPaymentMethodSnapshot($metodoPago);
                    
                    $pago = Pago::create(array_merge([
                        'fecha_pago' => now(),
                        'monto_pago' => $request->monto_total,
                        'estado_pago' => Pago::ESTADO_PENDIENTE,
                        'referencia_pago' => 'ORDER_' . $pedido->id_pedido . '_' . time(),
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'metodos_pago_id_metodo_pago' => $metodoPago->id_metodo_pago
                    ], $paymentMethodSnapshot));
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
            }            // Buscar pedido por ID numérico o por código de pedido
            $pedido = Pedido::with(['pedido_items.producto', 'pagos.metodos_pago', 'envios.direccion', 'envios.transportista', 'usuario'])
                           ->where(function($query) use ($pedidoId, $user) {
                               // Si es un número, buscar por id_pedido
                               if (is_numeric($pedidoId)) {
                                   $query->where('id_pedido', $pedidoId);
                               } else {
                                   // Si no es numérico, buscar por codigo_pedido
                                   $query->where('codigo_pedido', $pedidoId);
                               }
                           })
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
            if (!in_array($pedido->estado, [Pedido::ESTADO_PENDIENTE, Pedido::ESTADO_CONFIRMADO])) {
                return response()->json([
                    'error' => 'No se puede cancelar un pedido en estado: ' . $pedido->estado
                ], 400);
            }

            DB::beginTransaction();
            
            try {
                // Actualizar el estado del pedido a cancelado
                $pedido->update(['estado' => Pedido::ESTADO_CANCELADO]);

                // Cancelar pagos asociados que estén pendientes
                Pago::where('pedidos_id_pedido', $pedidoId)
                    ->whereIn('estado_pago', [Pago::ESTADO_PENDIENTE])
                    ->update(['estado_pago' => Pago::ESTADO_CANCELADO]);

                // Cancelar envíos asociados que estén pendientes o confirmados
                Envio::where('pedidos_id_pedido', $pedidoId)
                    ->whereIn('estado', [Envio::ESTADO_PENDIENTE, Envio::ESTADO_CONFIRMADO])
                    ->update(['estado' => Envio::ESTADO_CANCELADO]);

                // Restaurar stock si el pedido había sido pagado/confirmado
                if (in_array($pedido->estado, [Pedido::ESTADO_CONFIRMADO, Pedido::ESTADO_PREPARANDO, Pedido::ESTADO_EN_CAMINO])) {
                    $this->inventoryService->restoreStockForCancelledOrder($pedido);
                }

                DB::commit();

                Log::info("Pedido {$pedidoId} cancelado exitosamente por usuario {$user->id_usuario}");

                return response()->json([
                    'message' => 'Pedido cancelado exitosamente',
                    'order' => $pedido->fresh() // Recargar el pedido con los cambios
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
                    'pending_orders' => $pedidos->where('estado', Pedido::ESTADO_PENDIENTE)->count(),
                    'completed_orders' => $pedidos->where('estado', Pedido::ESTADO_ENTREGADO)->count(),
                    'cancelled_orders' => $pedidos->where('estado', Pedido::ESTADO_CANCELADO)->count()
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
                    'total_revenue' => Pedido::whereIn('estado', [Pedido::ESTADO_ENTREGADO])->sum('monto_total'),
                    'pending_orders' => Pedido::where('estado', Pedido::ESTADO_PENDIENTE)->count(),
                    'average_order_value' => round(Pedido::avg('monto_total'), 2)
                ],
                'by_status' => Pedido::selectRaw('estado, count(*) as count, sum(monto_total) as total_amount')
                    ->groupBy('estado')
                    ->get(),
                'monthly_sales' => Pedido::selectRaw('YEAR(fecha_creacion) as year, MONTH(fecha_creacion) as month, count(*) as orders_count, sum(monto_total) as total_sales')
                    ->whereIn('estado', [Pedido::ESTADO_ENTREGADO])
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
            $transportistaId = $this->assignTransporter();
            
            // 🔧 Obtener datos para snapshots
            $direccion = $addressId ? Direccion::find($addressId) : null;
            $transportista = DB::table('transportistas')->where('id_transportista', $transportistaId)->first();
            
            // Crear snapshots
            $addressSnapshot = $this->createAddressSnapshot($direccion);
            $transportistSnapshot = $this->createTransportistSnapshot($transportista);

            // Crear el registro de envío con estado "pendiente" y snapshots
            $envio = Envio::create(array_merge([
                'monto_envio' => $costoEnvio,
                'estado' => Envio::ESTADO_PENDIENTE, // Estado pendiente hasta que se confirme el pago
                'fecha_envio' => now(), // 🔧 Mismo día para fresas frescas (entrega en 1-2 horas)
                'transportistas_id_transportista' => $transportistaId,
                'pedidos_id_pedido' => $pedido->id_pedido,
                'direcciones_id_direccion' => $addressId // NUEVA RELACIÓN: FK en envios hacia direcciones
            ], $addressSnapshot, $transportistSnapshot));

            Log::info('Envío creado al momento del checkout con snapshots', [
                'pedido_id' => $pedido->id_pedido,
                'envio_id' => $envio->id_envio,
                'transportista_id' => $transportistaId,
                'transportista_nombre' => $transportistSnapshot['transportista_nombre_snapshot'],
                'monto_envio' => $costoEnvio,
                'estado' => Envio::ESTADO_PENDIENTE,
                'direccion_id' => $addressId,
                'direccion_snapshot' => $addressSnapshot['direccion_linea1_snapshot'],
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



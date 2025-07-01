<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Carrito;
use App\Models\CarritoItems;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\PedidoItems;
use App\Models\Pago;
use App\Models\Envio;
use App\Models\Direccion;
use App\Models\MetodosPago;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    // 🔧 MÉTODOS HELPER PARA SNAPSHOTS (reutilizados del PedidoController)
    
    /**
     * Crear snapshot de producto para pedido_items
     */
    private function createProductSnapshot(Producto $producto): array
    {
        return [
            'producto_nombre_snapshot' => $producto->nombre,
            'producto_descripcion_snapshot' => $producto->descripcion,
            'producto_imagen_snapshot' => $producto->url_imagen,
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
     * Obtener el carrito actual del usuario
     */
    public function index(): JsonResponse
    {
        try {
            $cart = Carrito::where('usuarios_id_usuario', Auth::id())
                       ->where('estado', 'activo')
                       ->first();

            if (!$cart) {
                $cart = Carrito::create([
                    'usuarios_id_usuario' => Auth::id(),
                    'estado' => 'activo',
                    'fecha_creacion' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Carrito recuperado exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al recuperar el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar un producto al carrito
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'producto_id' => 'required|exists:productos,id_producto',
            'cantidad' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Obtener o crear el carrito activo del usuario
            $cart = Carrito::firstOrCreate(
                [
                    'usuarios_id_usuario' => Auth::id(),
                    'estado' => 'activo'
                ],
                [
                    'fecha_creacion' => now()
                ]
            );

            // Verificar si el producto existe y está disponible
            $producto = Producto::find($request->producto_id);
            if (!$producto || $producto->estado !== 'activo') {
                return response()->json([
                    'success' => false,
                    'message' => 'El producto no está disponible'
                ], 400);
            }

            // Buscar si el producto ya está en el carrito
            $cartItem = CarritoItems::where('carritos_id_carrito', $cart->id_carrito)
                              ->where('productos_id_producto', $request->producto_id)
                              ->first();

            if ($cartItem) {
                // Actualizar cantidad si ya existe
                $cartItem->cantidad += $request->cantidad;
                $cartItem->save();
            } else {
                // Crear nuevo item si no existe
                $cartItem = CarritoItems::create([
                    'carritos_id_carrito' => $cart->id_carrito,
                    'productos_id_producto' => $request->producto_id,
                    'cantidad' => $request->cantidad
                ]);
            }

            // Recargar el carrito con sus items
            $cart->load('items.producto');

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al carrito exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el producto al carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar la cantidad de un producto en el carrito
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cantidad' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $cartItem = CarritoItems::findOrFail($id);
            
            // Verificar que el item pertenece al carrito del usuario
            $cart = Carrito::where('id_carrito', $cartItem->carritos_id_carrito)
                       ->where('usuarios_id_usuario', Auth::id())
                       ->where('estado', 'activo')
                       ->firstOrFail();

            if ($request->cantidad === 0) {
                // Eliminar el item si la cantidad es 0
                $cartItem->delete();
            } else {
                // Actualizar la cantidad
                $cartItem->cantidad = $request->cantidad;
                $cartItem->save();
            }

            // Recargar el carrito con sus items
            $cart->load('items.producto');

            return response()->json([
                'success' => true,
                'message' => 'Carrito actualizado exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un item del carrito
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $cartItem = CarritoItems::findOrFail($id);
            
            // Verificar que el item pertenece al carrito del usuario
            $cart = Carrito::where('id_carrito', $cartItem->carritos_id_carrito)
                       ->where('usuarios_id_usuario', Auth::id())
                       ->where('estado', 'activo')
                       ->firstOrFail();

            $cartItem->delete();

            // Recargar el carrito con sus items
            $cart->load('items.producto');

            return response()->json([
                'success' => true,
                'message' => 'Item eliminado del carrito exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el item del carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convertir carrito a pedido (checkout)
     */
    public function checkout(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'shipping_info' => 'required|array',
            'shipping_info.firstName' => 'required|string|max:100',
            'shipping_info.lastName' => 'required|string|max:100',
            'shipping_info.email' => 'required|email',
            'shipping_info.phone' => 'required|string|max:20',
            'shipping_info.address' => 'required|string|max:255',
            'shipping_info.city' => 'required|string|max:100',
            'shipping_info.postalCode' => 'required|string|max:10',
            'shipping_cost' => 'nullable|numeric|min:0',
            'address_info' => 'required|array',
            'address_info.type' => 'required|in:profile,new,select',
            'address_info.address_id' => 'required_if:address_info.type,profile,select|integer|exists:direcciones,id_direccion'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Obtener carrito activo del usuario
            $cart = Carrito::where('usuarios_id_usuario', Auth::id())
                          ->where('estado', 'activo')
                          ->with('items.producto')
                          ->first();

            if (!$cart || $cart->items->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El carrito está vacío'
                ], 400);
            }

            // Validar que todos los productos estén disponibles
            foreach ($cart->items as $item) {
                if (!$item->producto || $item->producto->estado !== 'activo') {
                    return response()->json([
                        'success' => false,
                        'message' => "El producto '{$item->producto->nombre}' ya no está disponible"
                    ], 400);
                }
            }

            DB::beginTransaction();

            try {
                // Calcular costo de envío antes de crear el pedido
                $shippingCost = $request->shipping_cost ?? $this->calculateShippingCost($cart);
                
                // Calcular total incluyendo envío
                $cartSubtotal = $cart->getTotal();
                $montoTotal = $cartSubtotal + $shippingCost;

                // Crear el pedido con el total que incluye envío
                $pedido = Pedido::create([
                    'monto_total' => $montoTotal,
                    'estado' => 'pendiente',
                    'fecha_creacion' => now(),
                    'usuarios_id_usuario' => Auth::id()
                ]);

                // Transferir items del carrito al pedido con snapshots
                foreach ($cart->items as $item) {
                    // 🔧 Crear snapshot del producto para preservar datos históricos
                    $productSnapshot = $this->createProductSnapshot($item->producto);
                    
                    PedidoItems::create(array_merge([
                        'cantidad' => $item->cantidad,
                        'precio' => $item->producto->precio,
                        'subtotal' => $item->cantidad * $item->producto->precio,
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'productos_id_producto' => $item->productos_id_producto
                    ], $productSnapshot));
                    
                    Log::info('Item del carrito transferido a pedido con snapshot', [
                        'pedido_id' => $pedido->id_pedido,
                        'producto_id' => $item->productos_id_producto,
                        'producto_nombre' => $productSnapshot['producto_nombre_snapshot'],
                        'categoria' => $productSnapshot['categoria_nombre_snapshot']
                    ]);
                }

                // Crear registro de pago inicial con snapshot
                $metodoPago = MetodosPago::find(1); // Mercado Pago por defecto
                if (!$metodoPago) {
                    $metodoPago = MetodosPago::where('activo', true)->first();
                }
                
                if ($metodoPago) {
                    // 🔧 Crear snapshot del método de pago para preservar datos históricos
                    $paymentMethodSnapshot = $this->createPaymentMethodSnapshot($metodoPago);
                    
                    $pago = Pago::create(array_merge([
                        'monto_pago' => $montoTotal,
                        'estado_pago' => 'pendiente',
                        'fecha_pago' => now(),
                        'metodos_pago_id_metodo_pago' => $metodoPago->id_metodo_pago,
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'referencia_pago' => 'PENDING_' . $pedido->id_pedido . '_' . time()
                    ], $paymentMethodSnapshot));
                }

                // Crear registro de envío
                $addressId = $request->address_info['address_id'] ?? null;
                
                $this->createShippingRecord($pedido, $shippingCost, $addressId);

                // Vaciar el carrito después de crear el pedido
                $cart->items()->delete();
                $cart->update(['estado' => 'completado']);

                DB::commit();

                // Cargar relaciones para la respuesta
                $pedido->load(['pedido_items.producto', 'usuario', 'pagos', 'envios']);

                return response()->json([
                    'success' => true,
                    'message' => 'Pedido creado exitosamente desde el carrito',
                    'data' => [
                        'order' => $pedido,
                        'order_id' => $pedido->id_pedido,
                        'payment' => $pago
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pedido desde el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear registro de envío desde carrito
     */
    private function createShippingRecord(Pedido $pedido, float $shippingCost, ?int $addressId): void
    {
        try {
            // Asignar transportista usando rotación
            $transportistaId = $this->assignTransporter();
            
            // 🔧 Obtener datos para snapshots
            $direccion = $addressId ? Direccion::find($addressId) : null;
            $transportista = DB::table('transportistas')->where('id_transportista', $transportistaId)->first();
            
            // Crear snapshots
            $addressSnapshot = $this->createAddressSnapshot($direccion);
            $transportistSnapshot = $this->createTransportistSnapshot($transportista);
            
            $envio = Envio::create(array_merge([
                'monto_envio' => $shippingCost,
                'estado' => 'pendiente',
                'fecha_envio' => now(), // 🔧 Mismo día para fresas frescas (entrega en 1-2 horas)
                'transportistas_id_transportista' => $transportistaId,
                'pedidos_id_pedido' => $pedido->id_pedido,
                'direcciones_id_direccion' => $addressId
            ], $addressSnapshot, $transportistSnapshot));

            // Log del envío creado con snapshots
            Log::info('Envío creado desde carrito con snapshots', [
                'pedido_id' => $pedido->id_pedido,
                'envio_id' => $envio->id_envio,
                'transportista_id' => $transportistaId,
                'transportista_nombre' => $transportistSnapshot['transportista_nombre_snapshot'],
                'monto_envio' => $shippingCost,
                'direccion_id' => $addressId,
                'direccion_snapshot' => $addressSnapshot['direccion_linea1_snapshot']
            ]);

        } catch (\Exception $e) {
            Log::error('Error al crear registro de envío desde carrito', [
                'pedido_id' => $pedido->id_pedido,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No lanzamos la excepción para que no falle el proceso de checkout
        }
    }

    /**
     * Calcular costo de envío desde el carrito con manejo de excepciones
     */
    private function calculateShippingCost(Carrito $cart): float
    {
        try {
            $strawberrySubtotal = 0;
            $cartTotal = 0;
            
            foreach ($cart->items as $item) {
                $itemSubtotal = $item->getSubtotal();
                $cartTotal += $itemSubtotal;
                
                // Sumar solo productos de fresas (categoría 1)
                if ($item->producto && $item->producto->categorias_id_categoria == 1) {
                    $strawberrySubtotal += $itemSubtotal;
                }
            }
            
            // 🔧 NUEVA LÓGICA: Envío gratis si:
            // 1. Total del carrito >= S/ 30 (cualquier producto) O
            // 2. Subtotal de fresas >= S/ 30 (condición original)
            $FREE_SHIPPING_THRESHOLD = 30.00;
            $hasCartTotalOffer = $cartTotal >= $FREE_SHIPPING_THRESHOLD;
            $hasStrawberryPackOffer = $strawberrySubtotal >= $FREE_SHIPPING_THRESHOLD;
            
            return ($hasCartTotalOffer || $hasStrawberryPackOffer) ? 0.00 : 5.00;
            
        } catch (\Exception $e) {
            Log::error('Error calculando costo de envío desde carrito', [
                'carrito_id' => $cart->id_carrito,
                'error' => $e->getMessage()
            ]);
            
            // Fallback: costo fijo en caso de error
            return 5.00;
        }
    }

    /**
     * Asignar transportista con rotación simple
     */
    private function assignTransporter(): int
    {
        try {
            // Obtener todos los transportistas disponibles
            $transportistas = DB::table('transportistas')->pluck('id_transportista')->toArray();
            
            if (empty($transportistas)) {
                Log::warning('No se encontraron transportistas, usando ID 1 por defecto');
                return 1;
            }
            
            // Obtener el último envío para ver cuál transportista fue asignado
            $ultimoEnvio = Envio::latest('id_envio')->first();
            
            if (!$ultimoEnvio) {
                // Si es el primer envío, usar el primer transportista
                Log::info('Primer envío, asignando transportista ID: ' . $transportistas[0]);
                return $transportistas[0];
            }
            
            // Encontrar el índice del último transportista usado
            $ultimoTransportistaIndex = array_search($ultimoEnvio->transportistas_id_transportista, $transportistas);
            
            // Si no se encuentra el transportista anterior, empezar desde el primero
            if ($ultimoTransportistaIndex === false) {
                Log::warning('Transportista anterior no encontrado, reiniciando rotación');
                return $transportistas[0];
            }
            
            // Asignar el siguiente transportista (rotación)
            $siguienteIndex = ($ultimoTransportistaIndex + 1) % count($transportistas);
            $siguienteTransportista = $transportistas[$siguienteIndex];
            
            Log::info('Rotación de transportistas', [
                'anterior_transportista' => $ultimoEnvio->transportistas_id_transportista,
                'nuevo_transportista' => $siguienteTransportista,
                'total_transportistas' => count($transportistas)
            ]);
            
            return $siguienteTransportista;
            
        } catch (\Exception $e) {
            Log::error('Error en rotación de transportistas', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback: usar ID 1 por defecto
            return 1;
        }
    }

    /**
     * Vaciar completamente el carrito del usuario
     */
    public function clearAll(): JsonResponse
    {
        try {
            // Encontrar el carrito activo del usuario
            $cart = Carrito::where('usuarios_id_usuario', Auth::id())
                       ->where('estado', 'activo')
                       ->first();
            
            // Si no hay carrito, devolver éxito
            if (!$cart) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay carrito que vaciar',
                    'data' => []
                ]);
            }

            // Eliminar todos los items del carrito
            CarritoItems::where('carritos_id_carrito', $cart->id_carrito)->delete();
            
            // Opcional: también podemos actualizar totales en la tabla de carrito
            $cart->update([
                'total' => 0
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Carrito vaciado exitosamente',
                'data' => []
            ]);

        } catch (\Exception $e) {
            Log::error('Error vaciando el carrito: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al vaciar el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
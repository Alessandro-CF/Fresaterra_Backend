<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pedido;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PedidoController extends Controller
{
    // Actualizar estado para admin usando constantes de enum
    public function adminUpdateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|string|in:' . implode(',', Pedido::getEstados())
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 400);
        }
        $pedido = \App\Models\Pedido::find($id);
        if (!$pedido) {
            return response()->json([
                'error' => 'Pedido no encontrado'
            ], 404);
        }
        
        // Guardar estado anterior para logs
        $estado_anterior = $pedido->estado;
        
        // Actualizar estado del pedido
        $pedido->estado = $request->estado;
        $pedido->save();
        
        // Sincronizar estado del envío automáticamente
        try {
            $envio = \App\Models\Envio::where('pedidos_id_pedido', $id)->first();
            if ($envio) {
                // Mapear estado del pedido al estado del envío
                $estado_envio = $this->mapearEstadoPedidoAEnvio($request->estado);
                $envio->estado = $estado_envio;
                $envio->save();
                
                Log::info("Estado sincronizado - Pedido: {$id}, Estado pedido: {$request->estado}, Estado envío: {$estado_envio}");
            }
        } catch (\Exception $e) {
            // Log el error pero no fallar la actualización del pedido
            Log::error("Error al sincronizar estado del envío para pedido {$id}: " . $e->getMessage());
        }
        
        return response()->json([
            'mensaje' => 'Estado actualizado y sincronizado',
            'id_pedido' => $pedido->id_pedido,
            'estado' => $pedido->estado,
            'estado_anterior' => $estado_anterior
        ], 200);
    }
    
    /**
     * Mapear estado del pedido al estado correspondiente del envío
     */
    private function mapearEstadoPedidoAEnvio($estado_pedido)
    {
        $mapeo = [
            \App\Models\Pedido::ESTADO_PENDIENTE => \App\Models\Envio::ESTADO_PENDIENTE,
            \App\Models\Pedido::ESTADO_CONFIRMADO => \App\Models\Envio::ESTADO_CONFIRMADO,
            \App\Models\Pedido::ESTADO_PREPARANDO => \App\Models\Envio::ESTADO_PREPARANDO,
            \App\Models\Pedido::ESTADO_EN_CAMINO => \App\Models\Envio::ESTADO_EN_CAMINO,
            \App\Models\Pedido::ESTADO_ENTREGADO => \App\Models\Envio::ESTADO_ENTREGADO,
            \App\Models\Pedido::ESTADO_CANCELADO => \App\Models\Envio::ESTADO_CANCELADO,
        ];

        return $mapeo[$estado_pedido] ?? $estado_pedido;
    }
    // Listar todos los pedidos con sus relaciones principales
    public function index()
    {
        $pedidos = \App\Models\Pedido::with(['usuario', 'envios', 'pagos', 'pedido_items'])->get();
        return response()->json([
            'message' => 'Pedidos obtenidos exitosamente',
            'orders' => $pedidos,
            'total' => $pedidos->count(),
        ]);
    }

    // Mostrar un pedido específico por ID o código
    public function show($id)
    {
        // Buscar por ID numérico o por código de pedido
        if (is_numeric($id)) {
            $pedido = \App\Models\Pedido::with(['usuario', 'envios', 'pagos', 'pedido_items'])->findOrFail($id);
        } else {
            $pedido = \App\Models\Pedido::with(['usuario', 'envios', 'pagos', 'pedido_items'])
                ->where('codigo_pedido', $id)
                ->firstOrFail();
        }
        
        return response()->json($pedido);
    }
    
    // Obtener pedidos por cliente específico
    public function getOrdersByClient($clientId)
    {
        try {
            $pedidos = \App\Models\Pedido::with(['usuario', 'envios', 'pagos', 'pedido_items.producto'])
                ->where('usuarios_id_usuario', $clientId)
                ->orderBy('fecha_creacion', 'desc')
                ->get();
            
            return response()->json([
                'message' => 'Pedidos del cliente obtenidos exitosamente',
                'orders' => $pedidos,
                'total' => $pedidos->count(),
                'client_id' => $clientId
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener pedidos del cliente',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Obtener todos los pedidos con filtros (Admin)
    public function getAllOrders(Request $request)
    {
        try {
            $query = \App\Models\Pedido::with(['usuario', 'pedido_items.producto', 'pagos.metodos_pago', 'envios.direccion', 'envios.transportista']);

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

            // Paginación
            $perPage = $request->get('per_page', 20);
            $pedidos = $query->orderBy('fecha_creacion', 'desc')->paginate($perPage);

            return response()->json([
                'message' => 'Pedidos obtenidos exitosamente',
                'orders' => $pedidos->items(),
                'pagination' => [
                    'current_page' => $pedidos->currentPage(),
                    'last_page' => $pedidos->lastPage(),
                    'per_page' => $pedidos->perPage(),
                    'total' => $pedidos->total()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener pedidos',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // Obtener estadísticas de pedidos (Admin)
    public function getOrderStatistics()
    {
        try {
            $stats = [
                'overview' => [
                    'total_orders' => \App\Models\Pedido::count(),
                    'total_revenue' => \App\Models\Pedido::whereIn('estado', [\App\Models\Pedido::ESTADO_ENTREGADO])->sum('monto_total'),
                    'pending_orders' => \App\Models\Pedido::where('estado', \App\Models\Pedido::ESTADO_PENDIENTE)->count(),
                    'average_order_value' => round(\App\Models\Pedido::avg('monto_total'), 2)
                ],
                'by_status' => \App\Models\Pedido::selectRaw('estado, count(*) as count, sum(monto_total) as total_amount')
                    ->groupBy('estado')
                    ->get(),
                'monthly_sales' => \App\Models\Pedido::selectRaw('YEAR(fecha_creacion) as year, MONTH(fecha_creacion) as month, count(*) as orders_count, sum(monto_total) as total_sales')
                    ->whereIn('estado', [\App\Models\Pedido::ESTADO_ENTREGADO])
                    ->groupByRaw('YEAR(fecha_creacion), MONTH(fecha_creacion)')
                    ->orderByRaw('YEAR(fecha_creacion) DESC, MONTH(fecha_creacion) DESC')
                    ->limit(12)
                    ->get(),
                'recent_orders' => \App\Models\Pedido::with(['usuario', 'pedido_items.producto'])
                    ->orderBy('fecha_creacion', 'desc')
                    ->limit(10)
                    ->get()
            ];

            return response()->json([
                'message' => 'Estadísticas obtenidas exitosamente',
                'statistics' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener estadísticas',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

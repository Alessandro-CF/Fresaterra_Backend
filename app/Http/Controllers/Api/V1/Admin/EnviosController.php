<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Envio;
use App\Models\Pedido;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class EnviosController extends Controller
{
    /**
     * Listar todos los envíos
     */
    public function index()
    {
        try {
            $envios = Envio::with(['pedido', 'transportista', 'direccion'])->get();
            
            return response()->json([
                'message' => 'Envíos obtenidos exitosamente',
                'shipments' => $envios,
                'total' => $envios->count(),
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener envíos: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener envíos'
            ], 500);
        }
    }

    /**
     * Mostrar un envío específico por ID
     */
    public function show($id)
    {
        try {
            $envio = Envio::with(['pedido', 'transportista', 'direccion'])->findOrFail($id);
            
            return response()->json([
                'message' => 'Envío obtenido exitosamente',
                'shipment' => $envio
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener envío: ' . $e->getMessage());
            return response()->json([
                'error' => 'Envío no encontrado'
            ], 404);
        }
    }

    /**
     * Obtener envío por ID de pedido
     */
    public function getByOrder($order_id)
    {
        try {
            $envio = Envio::with(['pedido', 'transportista', 'direccion'])
                          ->where('pedidos_id_pedido', $order_id)
                          ->first();
            
            if (!$envio) {
                return response()->json([
                    'error' => 'No se encontró envío para este pedido'
                ], 404);
            }
            
            return response()->json([
                'message' => 'Envío obtenido exitosamente',
                'data' => $envio
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Error al obtener envío por pedido: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener envío'
            ], 500);
        }
    }

    /**
     * Actualizar estado de un envío
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'estado' => 'required|string|in:' . implode(',', Envio::getEstados())
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $envio = Envio::find($id);
            
            if (!$envio) {
                return response()->json([
                    'error' => 'Envío no encontrado'
                ], 404);
            }

            $envio->estado = $request->estado;
            $envio->save();

            return response()->json([
                'message' => 'Estado de envío actualizado exitosamente',
                'shipment' => $envio
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al actualizar estado del envío: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el estado del envío'
            ], 500);
        }
    }

    /**
     * Sincronizar estado del envío con el estado del pedido
     */
    public function syncWithOrder(Request $request, $order_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'estado_pedido' => 'required|string|in:' . implode(',', Pedido::getEstados())
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            // Buscar el envío asociado al pedido
            $envio = Envio::where('pedidos_id_pedido', $order_id)->first();
            
            if (!$envio) {
                return response()->json([
                    'error' => 'No se encontró envío para este pedido'
                ], 404);
            }

            // Mapear estado del pedido al estado del envío
            $estado_envio = $this->mapearEstadoPedidoAEnvio($request->estado_pedido);

            // Actualizar el estado del envío
            $envio->estado = $estado_envio;
            $envio->save();

            return response()->json([
                'message' => 'Estado de envío sincronizado exitosamente',
                'shipment' => $envio,
                'estado_anterior' => $envio->getOriginal('estado'),
                'estado_nuevo' => $estado_envio
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al sincronizar estado del envío: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al sincronizar el estado del envío'
            ], 500);
        }
    }

    /**
     * Mapear estado del pedido al estado correspondiente del envío
     */
    private function mapearEstadoPedidoAEnvio($estado_pedido)
    {
        $mapeo = [
            Pedido::ESTADO_PENDIENTE => Envio::ESTADO_PENDIENTE,
            Pedido::ESTADO_CONFIRMADO => Envio::ESTADO_CONFIRMADO,
            Pedido::ESTADO_PREPARANDO => Envio::ESTADO_PREPARANDO,
            Pedido::ESTADO_EN_CAMINO => Envio::ESTADO_EN_CAMINO,
            Pedido::ESTADO_ENTREGADO => Envio::ESTADO_ENTREGADO,
            Pedido::ESTADO_CANCELADO => Envio::ESTADO_CANCELADO,
        ];

        return $mapeo[$estado_pedido] ?? $estado_pedido;
    }
}

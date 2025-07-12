<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pedido;
use App\Models\Pago;
use App\Models\Envio;
use App\Models\MetodosPago;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Log;

class OrderStatusController extends Controller
{
    /**
     * Actualizar el estado de un pedido
     * PATCH /orders/{id}/status
     */
    public function updateStatus(Request $request, $pedidoId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'estado' => 'required|string|in:pendiente,confirmado,preparando,en_camino,entregado,cancelado'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $pedido = Pedido::where('id_pedido', $pedidoId)
                         ->where('usuarios_id_usuario', $user->id_usuario)
                         ->first();

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido no encontrado'
                ], 404);
            }

            // Actualizar el estado
            $pedido->update(['estado' => $request->estado]);

            // Si se marca como cancelado, actualizamos también el pago
            if ($request->estado === 'cancelado') {
                Pago::where('pedidos_id_pedido', $pedidoId)
                    ->where('estado_pago', 'pendiente')
                    ->update(['estado_pago' => 'cancelado']);
            }

            return response()->json([
                'message' => 'Estado de pedido actualizado exitosamente',
                'order' => $pedido
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Error al actualizar estado del pedido: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el estado del pedido'
            ], 500);
        }
    }

    /**
     * Obtener el estado de un pedido
     * GET /orders/{id}/status
     */
    public function getStatus($pedidoId)
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
            }            return response()->json([
                'status' => $pedido->estado,
                'estado' => $pedido->estado,  // Para compatibilidad
                'id_pedido' => $pedido->id_pedido
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token inválido'
            ], 401);
        } catch (\Exception $e) {
            Log::error('Error al obtener estado del pedido: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener el estado del pedido'
            ], 500);
        }
    }

    /**
     * Reanudar un pedido pendiente
     * POST /orders/{id}/resume
     */
    public function resumeOrder($pedidoId)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $pedido = Pedido::with(['pedido_items.producto', 'pagos.metodos_pago'])
                         ->where('id_pedido', $pedidoId)
                         ->where('usuarios_id_usuario', $user->id_usuario)
                         ->where('estado', 'pendiente') // Solo pedidos pendientes
                         ->first();

            if (!$pedido) {
                return response()->json([
                    'error' => 'Pedido pendiente no encontrado o no se puede reanudar'
                ], 404);
            }

            DB::beginTransaction();

            try {
                // El pedido ya está en pendiente, no necesitamos cambiar el estado
                // Solo verificamos que el pago esté en estado pendiente
                $pago = Pago::where('pedidos_id_pedido', $pedidoId)
                           ->where('estado_pago', 'pendiente')
                           ->first();
                
                if (!$pago) {
                    // Si no hay pago pendiente, crear uno nuevo con snapshot
                    $metodoPago = MetodosPago::where('nombre', 'Mercado Pago')->first();
                    
                    if (!$metodoPago) {
                        $metodoPago = MetodosPago::where('activo', true)->first();
                    }
                    
                    // 🔧 Crear snapshot del método de pago
                    $paymentMethodSnapshot = [
                        'metodo_pago_nombre_snapshot' => $metodoPago->nombre
                    ];
                    
                    Pago::create(array_merge([
                        'fecha_pago' => now(),
                        'monto_pago' => $pedido->monto_total,
                        'estado_pago' => 'pendiente',
                        'referencia_pago' => 'ORDER_RESUME_' . $pedido->id_pedido . '_' . time(),
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'metodos_pago_id_metodo_pago' => $metodoPago->id_metodo_pago
                    ], $paymentMethodSnapshot));
                }
                
                DB::commit();
                
                return response()->json([
                    'message' => 'Pedido verificado, listo para completar el pago',
                    'order' => $pedido
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
            Log::error('Error al reanudar el pedido: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al reanudar el pedido: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Programar cancelación automática de pedidos pendientes vencidos
     * Se puede ejecutar como un comando artisan programado
     */
    public function cancelExpiredPendingOrders()
    {
        try {
            // Buscar pedidos pendientes con más de 2 horas de antigüedad
            $expirationTime = now()->subHours(2);
            $pendingOrders = Pedido::where('estado', 'pendiente')
                                ->where('fecha_creacion', '<', $expirationTime)
                                ->get();
            
            $count = 0;
            
            foreach ($pendingOrders as $pedido) {
                // Actualizar el pedido a "cancelado"
                $pedido->update(['estado' => 'cancelado']);
                
                // Actualizar los pagos asociados
                Pago::where('pedidos_id_pedido', $pedido->id_pedido)
                    ->where('estado_pago', 'pendiente')
                    ->update(['estado_pago' => 'cancelado']);
                
                $count++;
                
                // Opcional: notificar al usuario
            }
            
            return response()->json([
                'message' => $count . ' pedidos pendientes caducados fueron cancelados automáticamente'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error cancelando pedidos expirados: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error al cancelar pedidos expirados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar un pedido como cancelado (legacy endpoint, usar cancel en PedidoController)
     * POST /orders/{id}/mark-abandoned
     */
    public function markAsAbandoned(Request $request, $pedidoId)
    {
        try {
            // Validar que el pedido existe y está en estado pendiente
            $pedido = Pedido::where('id_pedido', $pedidoId)
                         ->where('estado', 'pendiente')
                         ->first();

            if (!$pedido) {
                Log::warning("Intento de marcar como cancelado pedido inexistente o no pendiente: {$pedidoId}");
                return response()->json([
                    'error' => 'Pedido no encontrado o no está en estado pendiente'
                ], 404);
            }

            // Verificar que el pedido sea reciente (menos de 24 horas)
            $maxTime = now()->subHours(24);
            if ($pedido->created_at < $maxTime) {
                Log::warning("Intento de marcar como cancelado pedido muy antiguo: {$pedidoId}");
                return response()->json([
                    'error' => 'El pedido es demasiado antiguo para ser cancelado automáticamente'
                ], 400);
            }

            // Verificar que no hay pagos completados asociados
            $pagoCompletado = Pago::where('pedidos_id_pedido', $pedidoId)
                                ->where('estado_pago', 'completado')
                                ->first();

            if ($pagoCompletado) {
                Log::warning("Intento de marcar como cancelado pedido con pago completado: {$pedidoId}");
                return response()->json([
                    'error' => 'No se puede cancelar un pedido con pago completado'
                ], 400);
            }

            // Marcar como cancelado
            $pedido->update(['estado' => 'cancelado']);

            // También marcar el pago como cancelado si existe
            Pago::where('pedidos_id_pedido', $pedidoId)
                ->where('estado_pago', 'pendiente')
                ->update(['estado_pago' => 'cancelado']);

            Log::info("Pedido {$pedidoId} marcado como cancelado exitosamente");

            return response()->json([
                'message' => 'Pedido marcado como cancelado exitosamente',
                'order_id' => $pedidoId,
                'new_status' => 'cancelado'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al marcar pedido como cancelado: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }
}

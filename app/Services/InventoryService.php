<?php

namespace App\Services;

use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\PedidoItems;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para manejar operaciones de inventario
 * 
 * Este servicio se encarga de:
 * - Reducir stock cuando se confirma un pago
 * - Restaurar stock cuando se cancela un pedido
 * - Verificar disponibilidad de stock
 * - Manejar transacciones de inventario de forma segura
 */
class InventoryService
{
    /**
     * Reducir stock cuando se confirma un pago y el pedido está confirmado
     * 
     * @param Pago $pago
     * @return bool
     */
    public function processPaymentStockReduction(Pago $pago): bool
    {
        try {
            // Verificar que el pago esté completado
            if ($pago->estado_pago !== Pago::ESTADO_COMPLETADO) {
                Log::info("InventoryService: Pago {$pago->id_pago} no está completado, no se reduce stock");
                return false;
            }

            // Cargar el pedido asociado
            $pedido = $pago->pedido;
            if (!$pedido) {
                Log::error("InventoryService: No se encontró pedido para pago {$pago->id_pago}");
                return false;
            }

            // Verificar que el pedido esté confirmado
            if ($pedido->estado !== Pedido::ESTADO_CONFIRMADO) {
                Log::info("InventoryService: Pedido {$pedido->id_pedido} no está confirmado, no se reduce stock");
                return false;
            }

            return DB::transaction(function () use ($pedido, $pago) {
                return $this->reduceStockForOrder($pedido, $pago);
            });

        } catch (Exception $e) {
            Log::error("InventoryService: Error procesando reducción de stock para pago {$pago->id_pago}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reducir stock para todos los items de un pedido
     * 
     * @param Pedido $pedido
     * @param Pago $pago
     * @return bool
     */
    private function reduceStockForOrder(Pedido $pedido, Pago $pago): bool
    {
        $pedidoItems = $pedido->pedido_items;
        
        if ($pedidoItems->isEmpty()) {
            Log::warning("InventoryService: Pedido {$pedido->id_pedido} no tiene items");
            return false;
        }

        foreach ($pedidoItems as $item) {
            $success = $this->reduceProductStock($item->productos_id_producto, $item->cantidad);
            
            if (!$success) {
                // Si falla uno, hacer rollback (la transacción se encarga)
                throw new Exception("No se pudo reducir stock para producto {$item->productos_id_producto}");
            }
        }

        Log::info("InventoryService: Stock reducido exitosamente para pedido {$pedido->id_pedido}, pago {$pago->id_pago}");
        return true;
    }

    /**
     * Reducir stock de un producto específico
     * 
     * @param int $productoId
     * @param int $cantidad
     * @return bool
     */
    public function reduceProductStock(int $productoId, int $cantidad): bool
    {
        try {
            $inventario = Inventario::where('productos_id_producto', $productoId)->first();
            
            if (!$inventario) {
                Log::warning("InventoryService: No se encontró inventario para producto {$productoId}");
                return false;
            }

            // Verificar stock disponible
            if ($inventario->cantidad_disponible < $cantidad) {
                Log::warning("InventoryService: Stock insuficiente para producto {$productoId}. Disponible: {$inventario->cantidad_disponible}, Solicitado: {$cantidad}");
                return false;
            }

            // Reducir stock
            $inventario->cantidad_disponible -= $cantidad;
            $inventario->ultima_actualizacion = now();
            
            // Si se agota, cambiar estado
            if ($inventario->cantidad_disponible <= 0) {
                $inventario->estado = 'agotado';
            }

            $inventario->save();

            Log::info("InventoryService: Stock reducido para producto {$productoId}. Cantidad reducida: {$cantidad}, Stock restante: {$inventario->cantidad_disponible}");
            return true;

        } catch (Exception $e) {
            Log::error("InventoryService: Error reduciendo stock para producto {$productoId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Restaurar stock cuando se cancela un pedido
     * 
     * @param Pedido $pedido
     * @return bool
     */
    public function restoreStockForCancelledOrder(Pedido $pedido): bool
    {
        try {
            return DB::transaction(function () use ($pedido) {
                $pedidoItems = $pedido->pedido_items;
                
                foreach ($pedidoItems as $item) {
                    $this->increaseProductStock($item->productos_id_producto, $item->cantidad);
                }

                Log::info("InventoryService: Stock restaurado para pedido cancelado {$pedido->id_pedido}");
                return true;
            });

        } catch (Exception $e) {
            Log::error("InventoryService: Error restaurando stock para pedido {$pedido->id_pedido}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Aumentar stock de un producto específico
     * 
     * @param int $productoId
     * @param int $cantidad
     * @return bool
     */
    public function increaseProductStock(int $productoId, int $cantidad): bool
    {
        try {
            $inventario = Inventario::where('productos_id_producto', $productoId)->first();
            
            if (!$inventario) {
                Log::warning("InventoryService: No se encontró inventario para producto {$productoId}");
                return false;
            }

            // Aumentar stock
            $inventario->cantidad_disponible += $cantidad;
            $inventario->ultima_actualizacion = now();
            
            // Si vuelve a tener stock, cambiar estado
            if ($inventario->cantidad_disponible > 0 && $inventario->estado === 'agotado') {
                $inventario->estado = 'disponible';
            }

            $inventario->save();

            Log::info("InventoryService: Stock aumentado para producto {$productoId}. Cantidad agregada: {$cantidad}, Stock actual: {$inventario->cantidad_disponible}");
            return true;

        } catch (Exception $e) {
            Log::error("InventoryService: Error aumentando stock para producto {$productoId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si hay stock suficiente para un pedido
     * 
     * @param array $items Array de ['producto_id' => cantidad, ...]
     * @return array ['available' => bool, 'details' => [...]]
     */
    public function checkStockAvailability(array $items): array
    {
        $result = [
            'available' => true,
            'details' => []
        ];

        foreach ($items as $productoId => $cantidadSolicitada) {
            $inventario = Inventario::where('productos_id_producto', $productoId)->first();
            $producto = Producto::find($productoId);
            
            $productDetail = [
                'producto_id' => $productoId,
                'producto_nombre' => $producto ? $producto->nombre : 'Producto no encontrado',
                'cantidad_solicitada' => $cantidadSolicitada,
                'cantidad_disponible' => $inventario ? $inventario->cantidad_disponible : 0,
                'disponible' => false
            ];

            if ($inventario && $inventario->cantidad_disponible >= $cantidadSolicitada && $inventario->estado === 'disponible') {
                $productDetail['disponible'] = true;
            } else {
                $result['available'] = false;
            }

            $result['details'][] = $productDetail;
        }

        return $result;
    }

    /**
     * Verificar stock disponible para un producto específico
     * 
     * @param int $productoId
     * @param int $cantidad
     * @return bool
     */
    public function isStockAvailable(int $productoId, int $cantidad): bool
    {
        $inventario = Inventario::where('productos_id_producto', $productoId)->first();
        
        return $inventario && 
               $inventario->cantidad_disponible >= $cantidad && 
               $inventario->estado === 'disponible';
    }

    /**
     * Obtener información de stock para un producto
     * 
     * @param int $productoId
     * @return array|null
     */
    public function getProductStockInfo(int $productoId): ?array
    {
        $inventario = Inventario::where('productos_id_producto', $productoId)->first();
        
        if (!$inventario) {
            return null;
        }

        return [
            'producto_id' => $productoId,
            'cantidad_disponible' => $inventario->cantidad_disponible,
            'estado' => $inventario->estado,
            'fecha_ingreso' => $inventario->fecha_ingreso,
            'ultima_actualizacion' => $inventario->ultima_actualizacion,
            'en_stock' => $inventario->cantidad_disponible > 0 && $inventario->estado === 'disponible'
        ];
    }
}

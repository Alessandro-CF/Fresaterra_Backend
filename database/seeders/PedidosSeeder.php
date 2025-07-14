<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Models\Pedido;

class PedidosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener usuarios clientes activos
        $usuarios = DB::table('users')->where('roles_id_rol', 2)->where('estado', true)->get();
        
        // Obtener productos disponibles
        $productos = DB::table('productos')->where('estado', 'activo')->get();
        
        if ($productos->isEmpty()) {
            throw new \Exception('No hay productos disponibles. Ejecuta ProductosSeeder primero.');
        }

        // Estados posibles para pedidos usando constantes del modelo
        $estadosPedido = Pedido::getEstados();

        // Configuración de pedidos por usuario
        $pedidosPorUsuario = [
            'cliente.activo@test.com' => [
                ['estado' => Pedido::ESTADO_PENDIENTE, 'dias_atras' => 0, 'productos' => [0, 1], 'cantidades' => [2, 1]],
                ['estado' => Pedido::ESTADO_ENTREGADO, 'dias_atras' => 10, 'productos' => [2], 'cantidades' => [1]],
                ['estado' => Pedido::ESTADO_CONFIRMADO, 'dias_atras' => 2, 'productos' => [0, 2], 'cantidades' => [1, 2]]
            ],
            'maria.garcia@test.com' => [
                ['estado' => Pedido::ESTADO_EN_CAMINO, 'dias_atras' => 1, 'productos' => [1, 3], 'cantidades' => [1, 1]],
                ['estado' => Pedido::ESTADO_ENTREGADO, 'dias_atras' => 15, 'productos' => [0], 'cantidades' => [3]]
            ],
            'juan.perez@test.com' => [
                ['estado' => Pedido::ESTADO_PREPARANDO, 'dias_atras' => 1, 'productos' => [2, 3], 'cantidades' => [2, 1]],
                ['estado' => Pedido::ESTADO_CANCELADO, 'dias_atras' => 7, 'productos' => [1], 'cantidades' => [1]]
            ],
            'ana.rodriguez@test.com' => [
                ['estado' => Pedido::ESTADO_CONFIRMADO, 'dias_atras' => 0, 'productos' => [0, 1, 2], 'cantidades' => [1, 1, 1]],
                ['estado' => Pedido::ESTADO_ENTREGADO, 'dias_atras' => 20, 'productos' => [3], 'cantidades' => [2]]
            ],
            'carlos.mendoza@test.com' => [
                ['estado' => Pedido::ESTADO_EN_CAMINO, 'dias_atras' => 3, 'productos' => [1, 2], 'cantidades' => [2, 1]],
            ],
            'sofia.vargas@test.com' => [
                ['estado' => Pedido::ESTADO_PENDIENTE, 'dias_atras' => 0, 'productos' => [0, 1, 2, 3], 'cantidades' => [1, 1, 1, 1]],
                ['estado' => Pedido::ESTADO_PREPARANDO, 'dias_atras' => 1, 'productos' => [0, 3], 'cantidades' => [2, 1]]
            ]
        ];

        foreach ($pedidosPorUsuario as $email => $pedidos) {
            $usuario = DB::table('users')->where('email', $email)->first();
            
            if ($usuario) {
                foreach ($pedidos as $pedidoData) {
                    $fechaCreacion = Carbon::now()->subDays($pedidoData['dias_atras']);
                    
                    // Calcular monto total
                    $montoTotal = 0;
                    foreach ($pedidoData['productos'] as $index => $productoIndex) {
                        if (isset($productos[$productoIndex])) {
                            $producto = $productos[$productoIndex];
                            $cantidad = $pedidoData['cantidades'][$index];
                            $montoTotal += $producto->precio * $cantidad;
                        }
                    }

                    // Crear pedido
                    $pedidoId = DB::table('pedidos')->insertGetId([
                        'monto_total' => $montoTotal,
                        'estado' => $pedidoData['estado'],
                        'fecha_creacion' => $fechaCreacion,
                        'usuarios_id_usuario' => $usuario->id_usuario,
                        'created_at' => $fechaCreacion,
                        'updated_at' => now(),
                    ]);

                    // Crear items del pedido
                    foreach ($pedidoData['productos'] as $index => $productoIndex) {
                        if (isset($productos[$productoIndex])) {
                            $producto = $productos[$productoIndex];
                            $cantidad = $pedidoData['cantidades'][$index];
                            $subtotal = $producto->precio * $cantidad;

                            // Obtener categoría del producto
                            $categoria = DB::table('categorias')
                                ->where('id_categoria', $producto->categorias_id_categoria)
                                ->first();

                            DB::table('pedido_items')->insert([
                                'cantidad' => $cantidad,
                                'precio' => $producto->precio,
                                'subtotal' => $subtotal,
                                'pedidos_id_pedido' => $pedidoId,
                                'productos_id_producto' => $producto->id_producto,
                                // Snapshots
                                'producto_nombre_snapshot' => $producto->nombre,
                                'producto_descripcion_snapshot' => $producto->descripcion,
                                'producto_imagen_snapshot' => $producto->url_imagen,
                                'producto_peso_snapshot' => $producto->peso,
                                'categoria_nombre_snapshot' => $categoria ? $categoria->nombre : 'Sin categoría',
                                'created_at' => $fechaCreacion,
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
    }
}

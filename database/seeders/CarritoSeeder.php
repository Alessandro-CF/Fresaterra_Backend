<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CarritoSeeder extends Seeder
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

        // Estados posibles para carritos
        $estadosCarrito = ['activo', 'abandonado', 'convertido', 'expirado'];

        $carritosPorUsuario = [
            'cliente.activo@test.com' => [
                ['estado' => 'activo', 'dias_atras' => 0, 'productos' => [1, 2]],
                ['estado' => 'convertido', 'dias_atras' => 5, 'productos' => [3]]
            ],
            'maria.garcia@test.com' => [
                ['estado' => 'activo', 'dias_atras' => 1, 'productos' => [1, 3, 4]],
            ],
            'juan.perez@test.com' => [
                ['estado' => 'abandonado', 'dias_atras' => 7, 'productos' => [2, 3]],
                ['estado' => 'activo', 'dias_atras' => 0, 'productos' => [1]]
            ],
            'ana.rodriguez@test.com' => [
                ['estado' => 'convertido', 'dias_atras' => 3, 'productos' => [2, 4]],
            ],
            'carlos.mendoza@test.com' => [
                ['estado' => 'expirado', 'dias_atras' => 15, 'productos' => [1, 2, 3]],
                ['estado' => 'activo', 'dias_atras' => 0, 'productos' => [4]]
            ],
            'sofia.vargas@test.com' => [
                ['estado' => 'activo', 'dias_atras' => 2, 'productos' => [1, 2, 3, 4]],
            ]
        ];

        foreach ($carritosPorUsuario as $email => $carritos) {
            $usuario = DB::table('users')->where('email', $email)->first();
            
            if ($usuario) {
                foreach ($carritos as $carritoData) {
                    $fechaCreacion = Carbon::now()->subDays($carritoData['dias_atras']);
                    
                    // Crear carrito
                    $carritoId = DB::table('carritos')->insertGetId([
                        'estado' => $carritoData['estado'],
                        'fecha_creacion' => $fechaCreacion,
                        'usuarios_id_usuario' => $usuario->id_usuario,
                        'created_at' => $fechaCreacion,
                        'updated_at' => now(),
                    ]);

                    // Agregar items al carrito
                    foreach ($carritoData['productos'] as $index => $productoIndex) {
                        if (isset($productos[$productoIndex - 1])) {
                            $producto = $productos[$productoIndex - 1];
                            $cantidad = rand(1, 3); // Cantidad aleatoria entre 1 y 3

                            DB::table('carrito_items')->insert([
                                'cantidad' => $cantidad,
                                'carritos_id_carrito' => $carritoId,
                                'productos_id_producto' => $producto->id_producto,
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

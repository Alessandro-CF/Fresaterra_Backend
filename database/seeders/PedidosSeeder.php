<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PedidosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener usuario de prueba (cliente activo)
        $usuario = DB::table('users')->where('email', 'cliente.activo@test.com')->first();
        if (!$usuario) {
            throw new \Exception('No se encontró el usuario de prueba. Ejecuta UserSeeder primero.');
        }

        // Obtener productos existentes
        $productos = DB::table('productos')->limit(2)->get();
        if ($productos->isEmpty()) {
            throw new \Exception('No hay productos en la base de datos. Ejecuta ProductosSeeder primero.');
        }

        // Obtener método de pago
        $metodoPago = DB::table('metodos_pago')->first();
        if (!$metodoPago) {
            throw new \Exception('No hay métodos de pago. Ejecuta MetodosPagoSeeder primero.');
        }

        // Crear dirección para el usuario (si no existe)
        $direccionId = DB::table('direcciones')->insertGetId([
            'calle' => 'Av. Falsa',
            'numero' => '123',
            'distrito' => 'Centro',
            'ciudad' => 'Cusco',
            'referencia' => 'Frente a la plaza',
            'predeterminada' => 'si',
            'usuarios_id_usuario' => $usuario->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear pedido realista
        $pedidoId = DB::table('pedidos')->insertGetId([
            'monto_total' => 36.00,
            'estado' => 'pendiente',
            'fecha_creacion' => Carbon::now(),
            'usuarios_id_usuario' => $usuario->id_usuario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear items del pedido (1 de cada producto)
        foreach ($productos as $producto) {
            DB::table('pedido_items')->insert([
                'cantidad' => 1,
                'precio' => $producto->precio,
                'subtotal' => $producto->precio,
                'pedidos_id_pedido' => $pedidoId,
                'productos_id_producto' => $producto->id_producto,
                // Snapshots
                'producto_nombre_snapshot' => $producto->nombre,
                'producto_descripcion_snapshot' => $producto->descripcion,
                'producto_imagen_snapshot' => $producto->url_imagen,
                'producto_peso_snapshot' => $producto->peso,
                'categoria_nombre_snapshot' => 'Fresas', // O usa la relación real si la necesitas
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Crear pago asociado al pedido
        DB::table('pagos')->insert([
            'fecha_pago' => Carbon::now(),
            'monto_pago' => 36.00,
            'estado_pago' => 'pendiente',
            'referencia_pago' => 'REF123456',
            'pedidos_id_pedido' => $pedidoId,
            'metodos_pago_id_metodo_pago' => $metodoPago->id_metodo_pago,
            'metodo_pago_nombre_snapshot' => $metodoPago->nombre,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

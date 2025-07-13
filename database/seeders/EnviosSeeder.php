<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Envio;
use App\Models\Pedido;

class EnviosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener pedidos que necesitan envío (excluir cancelados)
        $pedidos = DB::table('pedidos')
            ->whereNotIn('estado', [Pedido::ESTADO_CANCELADO])
            ->get();
        
        // Obtener transportistas disponibles
        $transportistas = DB::table('transportistas')->get();
        
        if ($transportistas->isEmpty()) {
            throw new \Exception('No hay transportistas disponibles. Ejecuta TransportistasSeeder primero.');
        }

        // Estados posibles para envíos usando constantes del modelo
        $estadosEnvio = Envio::getEstados();

        // Mapeo de estados de pedido a estados de envío más probables
        $mapeoEstados = [
            Pedido::ESTADO_PENDIENTE => [Envio::ESTADO_PENDIENTE],
            Pedido::ESTADO_CONFIRMADO => [Envio::ESTADO_CONFIRMADO, Envio::ESTADO_PREPARANDO],
            Pedido::ESTADO_PREPARANDO => [Envio::ESTADO_PREPARANDO],
            Pedido::ESTADO_EN_CAMINO => [Envio::ESTADO_EN_CAMINO],
            Pedido::ESTADO_ENTREGADO => [Envio::ESTADO_ENTREGADO]
        ];

        // Costos de envío por zona
        $costosEnvio = [
            'Centro Histórico' => 5.00,
            'Wanchaq' => 8.00,
            'Santiago' => 10.00,
            'San Blas' => 7.00,
            'San Sebastián' => 12.00,
            'San Jerónimo' => 15.00,
            'Saylla' => 20.00
        ];

        foreach ($pedidos as $pedido) {
            // Obtener dirección del usuario para este pedido
            $direccion = DB::table('direcciones')
                ->where('usuarios_id_usuario', $pedido->usuarios_id_usuario)
                ->where('predeterminada', 'si')
                ->first();

            if (!$direccion) {
                // Si no hay dirección predeterminada, tomar la primera disponible
                $direccion = DB::table('direcciones')
                    ->where('usuarios_id_usuario', $pedido->usuarios_id_usuario)
                    ->first();
            }

            if ($direccion) {            // Determinar estado de envío basado en estado del pedido
            $estadosPosibles = $mapeoEstados[$pedido->estado] ?? [Envio::ESTADO_PENDIENTE];
            $estadoEnvio = $estadosPosibles[array_rand($estadosPosibles)];
                
                // Seleccionar transportista aleatorio
                $transportista = $transportistas[array_rand($transportistas->toArray())];
                
                // Calcular costo de envío basado en el distrito
                $montoEnvio = $costosEnvio[$direccion->distrito] ?? 10.00;
                
                // Calcular fecha de envío
                $fechaPedido = Carbon::parse($pedido->fecha_creacion);
                $fechaEnvio = $fechaPedido;
                
                // Ajustar fecha según estado
                switch ($estadoEnvio) {
                    case 'pendiente':
                        $fechaEnvio = $fechaPedido; // Mismo día
                        break;
                    case 'preparando':
                        $fechaEnvio = $fechaPedido->addHours(rand(2, 24)); // 2-24 horas después
                        break;
                    case 'en_transito':
                        $fechaEnvio = $fechaPedido->addHours(rand(4, 48)); // 4-48 horas después
                        break;
                    case 'entregado':
                        $fechaEnvio = $fechaPedido->addHours(rand(24, 120)); // 1-5 días después
                        break;
                    case 'devuelto':
                        $fechaEnvio = $fechaPedido->addHours(rand(48, 168)); // 2-7 días después
                        break;
                    case 'extraviado':
                        $fechaEnvio = $fechaPedido->addHours(rand(72, 240)); // 3-10 días después
                        break;
                }

                // Verificar si ya existe un envío para este pedido
                $envioExistente = DB::table('envios')->where('pedidos_id_pedido', $pedido->id_pedido)->first();
                
                if (!$envioExistente) {
                    DB::table('envios')->insert([
                        'monto_envio' => $montoEnvio,
                        'estado' => $estadoEnvio,
                        'fecha_envio' => $fechaEnvio,
                        'transportistas_id_transportista' => $transportista->id_transportista,
                        'pedidos_id_pedido' => $pedido->id_pedido,
                        'direcciones_id_direccion' => $direccion->id_direccion,
                        // Snapshots
                        'direccion_linea1_snapshot' => $direccion->calle . ' ' . $direccion->numero,
                        'direccion_linea2_snapshot' => $direccion->referencia,
                        'direccion_ciudad_snapshot' => $direccion->ciudad,
                        'direccion_estado_snapshot' => $direccion->distrito,
                        'transportista_nombre_snapshot' => $transportista->nombre,
                        'transportista_telefono_snapshot' => $transportista->telefono,
                        'created_at' => $fechaEnvio,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}

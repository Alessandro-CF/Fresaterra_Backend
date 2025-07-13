<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Pago;
use App\Models\Pedido;

class PagosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los pedidos
        $pedidos = DB::table('pedidos')->get();
        
        // Obtener métodos de pago disponibles
        $metodosPago = DB::table('metodos_pago')->where('activo', true)->get();
        
        if ($metodosPago->isEmpty()) {
            throw new \Exception('No hay métodos de pago disponibles. Ejecuta MetodosPagoSeeder primero.');
        }

        // Estados posibles para pagos usando constantes del modelo
        $estadosPago = Pago::getEstados();

        // Mapeo de estados de pedido a estados de pago más probables
        $mapeoEstados = [
            Pedido::ESTADO_PENDIENTE => [Pago::ESTADO_PENDIENTE],
            Pedido::ESTADO_CONFIRMADO => [Pago::ESTADO_COMPLETADO],
            Pedido::ESTADO_PREPARANDO => [Pago::ESTADO_COMPLETADO],
            Pedido::ESTADO_EN_CAMINO => [Pago::ESTADO_COMPLETADO],
            Pedido::ESTADO_ENTREGADO => [Pago::ESTADO_COMPLETADO],
            Pedido::ESTADO_CANCELADO => [Pago::ESTADO_CANCELADO]
        ];

        foreach ($pedidos as $pedido) {
            // Determinar estado de pago basado en estado del pedido
            $estadosPosibles = $mapeoEstados[$pedido->estado] ?? [Pago::ESTADO_PENDIENTE];
            $estadoPago = $estadosPosibles[array_rand($estadosPosibles)];
            
            // Seleccionar método de pago aleatorio
            $metodoPago = $metodosPago[array_rand($metodosPago->toArray())];
            
            // Calcular fecha de pago
            $fechaPedido = Carbon::parse($pedido->fecha_creacion);
            $fechaPago = $fechaPedido;
            
            // Ajustar fecha según estado
            switch ($estadoPago) {
                case 'completado':
                    $fechaPago = $fechaPedido->addMinutes(rand(5, 120)); // 5 min a 2 horas después
                    break;
                case 'procesando':
                    $fechaPago = $fechaPedido->addMinutes(rand(1, 30)); // 1-30 min después
                    break;
                case 'fallido':
                case 'cancelado':
                    $fechaPago = $fechaPedido->addMinutes(rand(1, 60)); // 1-60 min después
                    break;
                case 'reembolsado':
                    $fechaPago = $fechaPedido->addHours(rand(24, 168)); // 1-7 días después
                    break;
            }

            // Generar referencia de pago única
            $referenciaPago = $this->generarReferenciaPago($metodoPago->nombre, $pedido->id_pedido);

            // Verificar si ya existe un pago para este pedido
            $pagoExistente = DB::table('pagos')->where('pedidos_id_pedido', $pedido->id_pedido)->first();
            
            if (!$pagoExistente) {
                DB::table('pagos')->insert([
                    'fecha_pago' => $fechaPago,
                    'monto_pago' => $pedido->monto_total,
                    'estado_pago' => $estadoPago,
                    'referencia_pago' => $referenciaPago,
                    'pedidos_id_pedido' => $pedido->id_pedido,
                    'metodos_pago_id_metodo_pago' => $metodoPago->id_metodo_pago,
                    'metodo_pago_nombre_snapshot' => $metodoPago->nombre,
                    'created_at' => $fechaPago,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Generar una referencia de pago única basada en el método de pago
     */
    private function generarReferenciaPago($metodoPago, $pedidoId): string
    {
        $prefijos = [
            'Tarjeta de Crédito' => 'TC',
            'Tarjeta de Débito' => 'TD',
            'PayPal' => 'PP',
            'Transferencia Bancaria' => 'TB',
            'Efectivo' => 'EF',
            'Yape' => 'YP',
            'Plin' => 'PL',
            'MercadoPago' => 'MP'
        ];

        $prefijo = $prefijos[$metodoPago] ?? 'REF';
        $timestamp = now()->format('ymdHis');
        $pedidoFormateado = str_pad($pedidoId, 4, '0', STR_PAD_LEFT);
        
        return $prefijo . $timestamp . $pedidoFormateado;
    }
}

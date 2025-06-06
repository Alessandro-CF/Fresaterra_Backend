<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MetodosPagoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $metodosPago = [
            [
                'id_metodo_pago' => 1,
                'nombre' => 'Mercado Pago',
                'activo' => true,
                'fecha_creacion' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_metodo_pago' => 2,
                'nombre' => 'Tarjeta de Crédito',
                'activo' => true,
                'fecha_creacion' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_metodo_pago' => 3,
                'nombre' => 'Tarjeta de Débito',
                'activo' => true,
                'fecha_creacion' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_metodo_pago' => 4,
                'nombre' => 'Transferencia Bancaria',
                'activo' => true,
                'fecha_creacion' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('metodos_pago')->insert($metodosPago);
    }
}

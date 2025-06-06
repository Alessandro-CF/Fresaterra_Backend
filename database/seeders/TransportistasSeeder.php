<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransportistasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('transportistas')->insert([
            [
                'id_transportista' => 1,
                'nombre' => 'Juan Pérez',
                'telefono' => '+593 99 123 4567',
                'tipo_transporte' => 'Motocicleta',
                'empresa' => 'Delivery Express',
                'placa_vehiculo' => 'ABC-123',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_transportista' => 2,
                'nombre' => 'María González',
                'telefono' => '+593 98 765 4321',
                'tipo_transporte' => 'Camioneta',
                'empresa' => 'Cargo Fast',
                'placa_vehiculo' => 'XYZ-789',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_transportista' => 3,
                'nombre' => 'Carlos Ruiz',
                'telefono' => '+593 97 555 1234',
                'tipo_transporte' => 'Bicicleta',
                'empresa' => null,
                'placa_vehiculo' => 'BIC-001',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}

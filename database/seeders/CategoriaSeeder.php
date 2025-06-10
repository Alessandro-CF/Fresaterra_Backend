<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Categoria;

class CategoriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categorias = [
            [
                'nombre' => 'Paquetes de Fresas',
                'descripcion' => 'Frutas frescas y de temporada, directamente del campo envasadas en paquetes separadas por su peso',
                'fecha_creacion' => now(),
            ],
            [
                'nombre' => 'Mermeladas',
                'descripcion' => 'Mermeladas artesanales elaboradas con fresas frescas y naturales',
                'fecha_creacion' => now(),
            ],
            [
                'nombre' => 'Fresas Deshidratadas',
                'descripcion' => 'Fresas deshidratadas, perfectas para snacks saludables',
                'fecha_creacion' => now(),
            ],
            [
                'nombre' => 'Combos Especiales',
                'descripcion' => 'Combos especiales de fresas y otros productos seleccionados',
                'fecha_creacion' => now(),
            ]
        ];

        foreach ($categorias as $categoria) {
            Categoria::create($categoria);
        }
    }
}

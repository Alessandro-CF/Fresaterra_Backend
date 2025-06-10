<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $productos = [
            [
                'id_producto' => 1,
                'nombre' => 'Delicia Andina - 1kg',
                'descripcion' => 'Ideal para un antojo o un detalle. Fresas frescas de invernadero local en Cusco, variedad Sabrina. Perfectas para consumo directo, snacks y loncheras.',
                'precio' => 13.00,
                'url_imagen' => '/img/paquete1kg1.jpg',
                'estado' => 'activo',
                'peso' => '1 kg',
                'fecha_creacion' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_producto' => 2,
                'nombre' => 'Doble Dulzura - 2kg',
                'descripcion' => 'Balance perfecto entre cantidad y precio. Fresas frescas de invernadero local en Cusco, variedad Sabrina. Ideales para batidos, postres y congelar por porciones.',
                'precio' => 23.00,
                'url_imagen' => '/img/paquete2kg1.png',
                'estado' => 'activo',
                'peso' => '2 kg',
                'fecha_creacion' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id_producto' => 3,
                'nombre' => 'Frescura Familiar - 5kg',
                'descripcion' => 'Para familias, eventos, repostería o congelar. Fresas frescas de invernadero local en Cusco, variedad Sabrina. Perfectas para repostería, jugos, conservas y consumo familiar.',
                'precio' => 52.00,
                'url_imagen' => '/img/paquete5kg1.png',
                'estado' => 'activo',
                'peso' => '5 kg',
                'fecha_creacion' => Carbon::now(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        DB::table('productos')->insert($productos);
    }
}

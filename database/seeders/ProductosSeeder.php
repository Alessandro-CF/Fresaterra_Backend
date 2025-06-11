<?php

namespace Database\Seeders;

use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all category IDs to randomly assign to products
        $categoriaIds = DB::table('categorias')->pluck('id_categoria')->toArray();
        
        // Ensure we have at least one category
        if (empty($categoriaIds)) {
            // If no categories exist, this will fail, so make sure CategoriaSeeder runs first
            throw new \Exception('No categories found in database. Run CategoriaSeeder first.');
        }
        
        // Default category ID if needed
        $defaultCategoriaId = $categoriaIds[0];
        
        $productos = [
            [
                'id_producto' => 1,
                'nombre' => 'Delicia Andina - 1kg',
                'descripcion' => 'Ideal para un antojo o un detalle. Fresas frescas de invernadero local en Cusco, variedad Sabrina. Perfectas para consumo directo, snacks y loncheras.',
                'precio' => 13,
                'peso' => '1 kg',
                'url_imagen' => '/img/paquete1kg1.jpg',
                'estado' => 'activo',
                'categorias_id_categoria' => $defaultCategoriaId, // Add the foreign key
                'fecha_creacion' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_producto' => 2,
                'nombre' => 'Doble Dulzura - 2kg',
                'descripcion' => 'Balance perfecto entre cantidad y precio. Fresas frescas de invernadero local en Cusco, variedad Sabrina. Ideales para batidos, postres y congelar por porciones.',
                'precio' => 23,
                'peso' => '2 kg',
                'url_imagen' => '/img/paquete2kg1.png',
                'estado' => 'activo',
                'categorias_id_categoria' => $defaultCategoriaId, // Add the foreign key
                'fecha_creacion' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id_producto' => 3,
                'nombre' => 'Frescura Familiar - 5kg',
                'descripcion' => 'Para familias, eventos, repostería o congelar. Fresas frescas de invernadero local en Cusco, variedad Sabrina. Perfectas para repostería, jugos, conservas y consumo familiar.',
                'precio' => 52,
                'peso' => '5 kg',
                'url_imagen' => '/img/paquete5kg1.png',
                'estado' => 'activo',
                'categorias_id_categoria' => $defaultCategoriaId, // Add the foreign key
                'fecha_creacion' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($productos as $producto) {
            Producto::create($producto);
        }
    }
}

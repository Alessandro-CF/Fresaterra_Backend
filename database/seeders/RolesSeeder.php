<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Insertar roles por defecto
        DB::table('roles')->insert([
            [
                'nombre' => 'administrador',
                'descripcion' => 'Usuario administrador con permisos completos',
                'created_at' => now(),
                'updated_at' => now(),
            ],
             [
                'nombre' => 'cliente',
                'descripcion' => 'Usuario cliente con permisos básicos',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'nombre' => 'invitado',
                'descripcion' => 'Usuario invitado con permisos limitados',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}

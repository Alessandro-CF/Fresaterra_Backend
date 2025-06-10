<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id_rol' => 1,
                'nombre' => 'admin',
                'descripcion' => 'Administrador del sistema',
            ],
            [
                'id_rol' => 2,
                'nombre' => 'user',
                'descripcion' => 'Usuario normal del sistema',
            ],
            [
                'id_rol' => 3,
                'nombre' => 'transportista',
                'descripcion' => 'Usuario transportista',
            ],
        ];

        foreach ($roles as $role) {
            \App\Models\Rol::updateOrCreate(
                ['id_rol' => $role['id_rol']],
                $role
            );
        }
    }
}

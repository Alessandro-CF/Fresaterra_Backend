<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario administrador (solo si no existe)
        if (!DB::table('users')->where('email', 'admin@fresaterra.com')->exists()) {
            DB::table('users')->insert([
                'nombre' => 'Admin',
                'apellidos' => 'Sistema',
                'email' => 'admin@fresaterra.com',
                'telefono' => '+51987654321',
                'password' => Hash::make('admin123456'),
                'roles_id_rol' => 1, // Administrador
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Crear usuario cliente de prueba (activo) - solo si no existe
        if (!DB::table('users')->where('email', 'cliente.activo@test.com')->exists()) {
            DB::table('users')->insert([
                'nombre' => 'Cliente',
                'apellidos' => 'Activo Test',
                'email' => 'cliente.activo@test.com',
                'telefono' => '+51123456789',
                'password' => Hash::make('password123456'),
                'roles_id_rol' => 2, // Cliente
                'estado' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Crear usuario cliente de prueba (desactivado) - solo si no existe
        if (!DB::table('users')->where('email', 'cliente.desactivado@test.com')->exists()) {
            DB::table('users')->insert([
                'nombre' => 'Cliente',
                'apellidos' => 'Desactivado Test',
                'email' => 'cliente.desactivado@test.com',
                'telefono' => '+51987123456',
                'password' => Hash::make('password123456'),
                'roles_id_rol' => 2, // Cliente
                'estado' => false, // Desactivado para testing
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Crear más usuarios de prueba para diversos casos de pagos y envíos
        $usuariosPrueba = [
            [
                'email' => 'maria.garcia@test.com',
                'nombre' => 'María',
                'apellidos' => 'García López',
                'telefono' => '+51987111222',
                'estado' => true,
            ],
            [
                'email' => 'juan.perez@test.com',
                'nombre' => 'Juan',
                'apellidos' => 'Pérez Silva',
                'telefono' => '+51987333444',
                'estado' => true,
            ],
            [
                'email' => 'ana.rodriguez@test.com',
                'nombre' => 'Ana',
                'apellidos' => 'Rodríguez Mamani',
                'telefono' => '+51987555666',
                'estado' => true,
            ],
            [
                'email' => 'carlos.mendoza@test.com',
                'nombre' => 'Carlos',
                'apellidos' => 'Mendoza Quispe',
                'telefono' => '+51987777888',
                'estado' => true,
            ],
            [
                'email' => 'sofia.vargas@test.com',
                'nombre' => 'Sofía',
                'apellidos' => 'Vargas Huamán',
                'telefono' => '+51987999000',
                'estado' => true,
            ]
        ];

        foreach ($usuariosPrueba as $usuario) {
            if (!DB::table('users')->where('email', $usuario['email'])->exists()) {
                DB::table('users')->insert([
                    'nombre' => $usuario['nombre'],
                    'apellidos' => $usuario['apellidos'],
                    'email' => $usuario['email'],
                    'telefono' => $usuario['telefono'],
                    'password' => Hash::make('password123456'),
                    'roles_id_rol' => 2, // Cliente
                    'estado' => $usuario['estado'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

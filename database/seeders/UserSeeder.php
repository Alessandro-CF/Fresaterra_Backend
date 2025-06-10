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
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Rol;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Rol::where('nombre', 'admin')->first();
        $userRole = Rol::where('nombre', 'user')->first();

        // Create Admin Users
        User::create([
            'nombre' => 'Royer',
            'apellidos' => 'Quispe Delgado',
            'email' => 'admin1@example.com',
            'password' => Hash::make('password123456'),
            'telefono' => '1234567890',
            'roles_id_rol' => $adminRole->id_rol
        ]);

        User::create([
            'nombre' => 'Alessandro',
            'apellidos' => 'Choque Fernandez',
            'email' => 'admin2@example.com',
            'password' => Hash::make('password123456'), // Updated to match 10-char requirement
            'telefono' => '0987654321',
            'roles_id_rol' => $adminRole->id_rol
        ]);

        // Create Regular Users
        User::create([
            'nombre' => 'Audad',
            'apellidos' => 'Zuniga leva',
            'email' => 'user1@example.com',
            'password' => Hash::make('password123456'), // Updated to match 10-char requirement
            'telefono' => '1122334455',
            'roles_id_rol' => $userRole->id_rol
        ]);

        User::create([
            'nombre' => 'Miguel',
            'apellidos' => 'Sairitupa Paucar',
            'email' => 'user2@example.com',
            'password' => Hash::make('password123456'), // Updated to match 10-char requirement
            'telefono' => '5544332211',
            'roles_id_rol' => $userRole->id_rol
        ]);
    }
}

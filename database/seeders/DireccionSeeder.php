<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DireccionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener todos los usuarios clientes
        $usuarios = DB::table('users')->where('roles_id_rol', 2)->get();

        $direcciones = [
            // Para cliente.activo@test.com
            [
                'email' => 'cliente.activo@test.com',
                'direcciones' => [
                    [
                        'calle' => 'Av. El Sol',
                        'numero' => '123',
                        'distrito' => 'Wanchaq',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Frente al mercado San Pedro',
                        'predeterminada' => 'si'
                    ],
                    [
                        'calle' => 'Jr. Pumacurco',
                        'numero' => '456',
                        'distrito' => 'San Blas',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Cerca de la iglesia',
                        'predeterminada' => 'no'
                    ]
                ]
            ],
            // Para maria.garcia@test.com
            [
                'email' => 'maria.garcia@test.com',
                'direcciones' => [
                    [
                        'calle' => 'Av. La Cultura',
                        'numero' => '789',
                        'distrito' => 'Santiago',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Al costado de la Universidad',
                        'predeterminada' => 'si'
                    ]
                ]
            ],
            // Para juan.perez@test.com
            [
                'email' => 'juan.perez@test.com',
                'direcciones' => [
                    [
                        'calle' => 'Calle Plateros',
                        'numero' => '234',
                        'distrito' => 'Centro Histórico',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Portal de Panes',
                        'predeterminada' => 'si'
                    ],
                    [
                        'calle' => 'Av. Tullumayo',
                        'numero' => '567',
                        'distrito' => 'San Sebastián',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Frente al parque',
                        'predeterminada' => 'no'
                    ]
                ]
            ],
            // Para ana.rodriguez@test.com
            [
                'email' => 'ana.rodriguez@test.com',
                'direcciones' => [
                    [
                        'calle' => 'Av. Garcilaso',
                        'numero' => '890',
                        'distrito' => 'San Jerónimo',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Cerca del centro comercial',
                        'predeterminada' => 'si'
                    ]
                ]
            ],
            // Para carlos.mendoza@test.com
            [
                'email' => 'carlos.mendoza@test.com',
                'direcciones' => [
                    [
                        'calle' => 'Calle Saphi',
                        'numero' => '345',
                        'distrito' => 'Centro Histórico',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Barrio de San Cristóbal',
                        'predeterminada' => 'si'
                    ]
                ]
            ],
            // Para sofia.vargas@test.com
            [
                'email' => 'sofia.vargas@test.com',
                'direcciones' => [
                    [
                        'calle' => 'Av. Regional',
                        'numero' => '678',
                        'distrito' => 'Saylla',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Zona industrial',
                        'predeterminada' => 'si'
                    ],
                    [
                        'calle' => 'Jr. Mariscal Gamarra',
                        'numero' => '901',
                        'distrito' => 'Wanchaq',
                        'ciudad' => 'Cusco',
                        'referencia' => 'Terminal terrestre',
                        'predeterminada' => 'no'
                    ]
                ]
            ]
        ];

        foreach ($direcciones as $direccionData) {
            $usuario = DB::table('users')->where('email', $direccionData['email'])->first();
            
            if ($usuario) {
                foreach ($direccionData['direcciones'] as $direccion) {
                    // Verificar si la dirección ya existe
                    $existeDir = DB::table('direcciones')
                        ->where('usuarios_id_usuario', $usuario->id_usuario)
                        ->where('calle', $direccion['calle'])
                        ->where('numero', $direccion['numero'])
                        ->exists();

                    if (!$existeDir) {
                        DB::table('direcciones')->insert([
                            'calle' => $direccion['calle'],
                            'numero' => $direccion['numero'],
                            'distrito' => $direccion['distrito'],
                            'ciudad' => $direccion['ciudad'],
                            'referencia' => $direccion['referencia'],
                            'predeterminada' => $direccion['predeterminada'],
                            'usuarios_id_usuario' => $usuario->id_usuario,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }
}

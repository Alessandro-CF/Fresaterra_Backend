<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class GenerateTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fresaterra:generate-test-data 
                          {--recent : Solo generar datos recientes de los últimos 30 días}
                          {--full : Generar conjunto completo de datos de prueba}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera datos de prueba para reportes de ventas y estadísticas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Generando datos de prueba para Fresaterra...');

        if ($this->option('recent')) {
            $this->info('📊 Generando solo datos recientes...');
            
            try {
                Artisan::call('db:seed', ['--class' => 'DatosRecientesSeeder']);
                $this->info('✅ Datos recientes generados exitosamente.');
            } catch (\Exception $e) {
                $this->error('❌ Error al generar datos recientes: ' . $e->getMessage());
                return 1;
            }
            
        } elseif ($this->option('full')) {
            $this->info('📦 Generando conjunto completo de datos...');
            
            $seeders = [
                'RolesSeeder' => 'Roles de usuario',
                'UserSeeder' => 'Usuarios de prueba',
                'DireccionesSeeder' => 'Direcciones de usuarios',
                'CategoriaSeeder' => 'Categorías de productos',
                'MetodosPagoSeeder' => 'Métodos de pago',
                'ProductosSeeder' => 'Productos',
                'TransportistasSeeder' => 'Transportistas',
                'PedidosSeeder' => 'Pedidos históricos',
                'DatosRecientesSeeder' => 'Datos recientes'
            ];

            foreach ($seeders as $seeder => $description) {
                $this->info("⏳ Ejecutando: {$description}...");
                
                try {
                    Artisan::call('db:seed', ['--class' => $seeder]);
                    $this->info("✅ {$description} - Completado");
                } catch (\Exception $e) {
                    $this->error("❌ Error en {$description}: " . $e->getMessage());
                    $this->warn("Continuando con el siguiente seeder...");
                }
            }
            
        } else {
            $this->info('📊 Generando datos básicos para reportes...');
            
            $basicSeeders = [
                'UserSeeder' => 'Usuarios adicionales',
                'DireccionesSeeder' => 'Direcciones',
                'PedidosSeeder' => 'Pedidos y transacciones',
                'DatosRecientesSeeder' => 'Datos recientes'
            ];

            foreach ($basicSeeders as $seeder => $description) {
                $this->info("⏳ {$description}...");
                
                try {
                    Artisan::call('db:seed', ['--class' => $seeder]);
                    $this->info("✅ {$description} - Completado");
                } catch (\Exception $e) {
                    $this->error("❌ Error en {$description}: " . $e->getMessage());
                }
            }
        }

        $this->newLine();
        $this->info('🎉 Proceso completado!');
        $this->info('💡 Los datos están listos para ser utilizados en los reportes de venta.');
        $this->newLine();
        $this->comment('Puedes probar los reportes con:');
        $this->line('- GET /api/v1/admin/reportes/ventas-resumen');
        $this->line('- GET /api/v1/admin/reports/data/charts');
        $this->line('- GET /api/v1/admin/reports/kpis');

        return 0;
    }
}

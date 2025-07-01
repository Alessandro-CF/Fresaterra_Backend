<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class TestWelcomeEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-welcome {email : Email donde enviar la prueba}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar correo de prueba de bienvenida para verificar configuración';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("🔄 Enviando correo de bienvenida a: {$email}");
        $this->info("📧 Configuración actual:");
        $this->line("   - MAIL_MAILER: " . config('mail.default'));
        $this->line("   - MAIL_HOST: " . config('mail.mailers.smtp.host'));
        $this->line("   - MAIL_FROM: " . config('mail.from.address'));
        $this->newLine();
        
        try {
            // Crear usuario ficticio para la prueba
            $testUser = new User([
                'nombre' => 'Usuario',
                'apellidos' => 'De Prueba',
                'email' => $email
            ]);
            
            // Enviar notificación
            Notification::route('mail', $email)
                ->notify(new WelcomeUserNotification($testUser));
            
            $this->info("✅ ¡Correo enviado exitosamente!");
            $this->info("📥 Revisa tu bandeja de entrada (y carpeta de spam) en: {$email}");
            $this->newLine();
            $this->info("📝 Si no funciona, verifica:");
            $this->line("   1. Que uses una 'Contraseña de aplicación' de Gmail (no tu contraseña normal)");
            $this->line("   2. Que la cuenta fresaterra@gmail.com exista");
            $this->line("   3. Que la verificación en 2 pasos esté activada en Gmail");
            
        } catch (\Exception $e) {
            $this->error("❌ Error enviando correo: " . $e->getMessage());
            $this->newLine();
            $this->info("💡 Soluciones:");
            $this->line("   1. Ve a Gmail → Configuración → Seguridad → Contraseñas de aplicación");
            $this->line("   2. Genera una nueva contraseña de aplicación");
            $this->line("   3. Usa esa contraseña en MAIL_PASSWORD (no tu contraseña normal)");
        }
    }
}

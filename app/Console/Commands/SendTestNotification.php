<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Models\User;

class SendTestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test 
                            {user_id : ID del usuario} 
                            {--type=general : Tipo de notificación} 
                            {--email : Enviar también por email}
                            {--message= : Mensaje personalizado}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enviar una notificación de prueba a un usuario específico';

    protected $notificacionController;

    public function __construct(NotificacionController $notificacionController)
    {
        parent::__construct();
        $this->notificacionController = $notificacionController;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id');
        $type = $this->option('type');
        $sendEmail = $this->option('email');
        $message = $this->option('message') ?: 'Esta es una notificación de prueba enviada desde la consola.';

        // Verificar que el usuario existe
        $user = User::find($userId);
        if (!$user) {
            $this->error("Usuario con ID {$userId} no encontrado.");
            return 1;
        }

        $this->info("Enviando notificación de prueba...");
        $this->info("Usuario: {$user->nombre} {$user->apellidos} ({$user->email})");
        $this->info("Tipo: {$type}");
        $this->info("Email: " . ($sendEmail ? 'Sí' : 'No'));

        $data = [
            'mensaje' => $message,
            'test' => true,
            'enviado_desde' => 'consola',
            'fecha_envio' => now()->toDateTimeString()
        ];

        $result = $this->notificationService->sendCompleteNotification(
            $userId,
            $data,
            $type,
            'Notificación de prueba - Fresaterra',
            $sendEmail
        );

        if ($result['success']) {
            $this->info('✅ Notificación enviada exitosamente!');
            
            if (isset($result['results']['inApp']) && $result['results']['inApp']['success']) {
                $this->line('📱 Notificación in-app: ✅ Enviada');
            }
            
            if ($sendEmail) {
                if (isset($result['results']['email']) && $result['results']['email']['success']) {
                    $this->line('📧 Notificación por email: ✅ Enviada');
                } else {
                    $this->warn('📧 Notificación por email: ❌ Error');
                }
            }
        } else {
            $this->error('❌ Error al enviar notificación: ' . $result['message']);
            return 1;
        }

        return 0;
    }
}

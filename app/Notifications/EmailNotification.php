<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;
    protected $tipo;
    protected $asunto;

    /**
     * Create a new notification instance.
     */
    public function __construct($data, $tipo = 'general', $asunto = null)
    {
        $this->data = $data;
        $this->tipo = $tipo;
        $this->asunto = $asunto ?? 'Nueva notificación de Fresaterra';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject($this->asunto)
            ->greeting('¡Hola ' . $notifiable->nombre . '!')
            ->line($this->data['mensaje'] ?? 'Tienes una nueva notificación.')
            ->line('Detalles:');        // Agregar información adicional según el tipo
        switch ($this->tipo) {
            case 'pedido':
                $mailMessage->line('Tipo: Actualización de pedido')
                           ->line('Estado: ' . ($this->data['estado'] ?? 'Pendiente'))
                           ->action('Ver pedido', url('/api/v1/orders/' . ($this->data['pedido_id'] ?? '')));
                break;
            
            case 'producto':
                $mailMessage->line('Tipo: Notificación de producto')
                           ->line('Producto: ' . ($this->data['producto_nombre'] ?? ''))
                           ->action('Ver producto', url('/api/v1/products/' . ($this->data['producto_id'] ?? '')));
                break;
            
            case 'promocion':
                $mailMessage->line('Tipo: Promoción especial');
                if (isset($this->data['descuento'])) {
                    $mailMessage->line('Oferta: ' . $this->data['descuento'] . '% de descuento');
                }
                $mailMessage->action('Ver promociones', url('/api/v1/products'));
                break;
            
            case 'registro':
                $mailMessage->line('Tipo: Confirmación de registro');
                if (isset($this->data['fecha_registro'])) {
                    $mailMessage->line('Fecha de registro: ' . $this->data['fecha_registro']);
                }
                if (isset($this->data['mensaje_bienvenida'])) {
                    $mailMessage->line($this->data['mensaje_bienvenida']);
                }
                if (isset($this->data['action_url'])) {
                    $mailMessage->action('Iniciar sesión', $this->data['action_url']);
                } else {
                    $mailMessage->action('Ir a la aplicación', url('/'));
                }
                break;
            
            case 'general':
            case 'sistema':
            case 'urgente':
            case 'novedad':
                $mailMessage->line('Tipo: ' . ucfirst($this->tipo));
                if (isset($this->data['fecha_notificacion'])) {
                    $mailMessage->line('Fecha: ' . $this->data['fecha_notificacion']);
                }
                if (isset($this->data['tipo_notificacion'])) {
                    $mailMessage->line('Categoría: ' . $this->data['tipo_notificacion']);
                }
                $mailMessage->action('Ir a la aplicación', url('/'));
                break;
            
            default:
                $mailMessage->line('Tipo: Notificación general')
                           ->action('Ir a la aplicación', url('/'));
                break;
        }

        $mailMessage->line('Gracias por usar Fresaterra!')
                    ->salutation('Saludos, El equipo de Fresaterra');

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => $this->tipo,
            'mensaje' => $this->data['mensaje'] ?? '',
            'data' => $this->data,
            'fecha' => now()->toDateTimeString(),
            'usuario_id' => $notifiable->id_usuario,
            'asunto' => $this->asunto
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}

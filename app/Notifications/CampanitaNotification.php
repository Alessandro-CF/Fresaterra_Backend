<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CampanitaNotification extends Notification
{
    use Queueable;

    protected $data;
    protected $tipo;

    /**
     * Create a new notification instance.
     */
    public function __construct($data, $tipo = 'general')
    {
        $this->data = $data;
        $this->tipo = $tipo;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
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
            'icon' => $this->getIconByType($this->tipo),
            'color' => $this->getColorByType($this->tipo)
        ];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    /**
     * Get icon based on notification type
     */
    protected function getIconByType($tipo): string
    {
        $icons = [
            'pedido' => 'shopping-cart',
            'producto' => 'package',
            'promocion' => 'tag',
            'mensaje' => 'message-circle',
            'sistema' => 'settings',
            'envio' => 'truck',
            'pago' => 'credit-card',
            'inventario' => 'archive',
            'general' => 'bell'
        ];

        return $icons[$tipo] ?? 'bell';
    }

    /**
     * Get color based on notification type
     */
    protected function getColorByType($tipo): string
    {
        $colors = [
            'pedido' => 'blue',
            'producto' => 'green',
            'promocion' => 'orange',
            'mensaje' => 'purple',
            'sistema' => 'gray',
            'envio' => 'yellow',
            'pago' => 'red',
            'inventario' => 'indigo',
            'general' => 'blue'
        ];

        return $colors[$tipo] ?? 'blue';
    }
}

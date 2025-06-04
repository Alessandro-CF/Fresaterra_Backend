<?php

namespace App\Services;

use App\Models\User;
use App\Models\Mensaje;
use App\Models\Notificacion;
use App\Notifications\CampanitaNotification;
use App\Notifications\EmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Enviar notificación tipo campanita (solo in-app)
     */
    public function sendInAppNotification($userId, $data, $tipo = 'general')
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Enviar notificación campanita
            $user->notify(new CampanitaNotification($data, $tipo));

            return [
                'success' => true,
                'message' => 'Notificación in-app enviada correctamente',
                'type' => 'campanita'
            ];
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación in-app: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar notificación: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Enviar notificación por email
     */
    public function sendEmailNotification($userId, $data, $tipo = 'general', $asunto = null)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Enviar notificación por email
            $user->notify(new EmailNotification($data, $tipo, $asunto));

            return [
                'success' => true,
                'message' => 'Notificación por email enviada correctamente',
                'type' => 'email'
            ];
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación por email: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al enviar notificación por email: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Enviar notificación completa (in-app + email)
     */
    public function sendCompleteNotification($userId, $data, $tipo = 'general', $asunto = null, $sendEmail = true)
    {
        $results = [];

        // Enviar notificación in-app siempre
        $inAppResult = $this->sendInAppNotification($userId, $data, $tipo);
        $results['inApp'] = $inAppResult;

        // Enviar email si se solicita
        if ($sendEmail) {
            $emailResult = $this->sendEmailNotification($userId, $data, $tipo, $asunto);
            $results['email'] = $emailResult;
        }

        $allSuccess = $inAppResult['success'] && (!$sendEmail || $results['email']['success']);

        return [
            'success' => $allSuccess,
            'message' => $allSuccess ? 'Notificaciones enviadas correctamente' : 'Algunas notificaciones fallaron',
            'results' => $results
        ];
    }

    /**
     * Crear notificación usando mensaje existente
     */
    public function createNotificationFromMessage($userId, $mensajeId, $tipo = 'general', $sendEmail = false)
    {
        try {
            $mensaje = Mensaje::find($mensajeId);
            if (!$mensaje) {
                throw new \Exception('Mensaje no encontrado');
            }

            $data = [
                'mensaje' => $mensaje->contenido,
                'mensaje_tipo' => $mensaje->tipo,
                'mensaje_id' => $mensaje->id_mensaje
            ];

            return $this->sendCompleteNotification($userId, $data, $tipo, null, $sendEmail);
        } catch (\Exception $e) {
            Log::error('Error al crear notificación desde mensaje: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al procesar mensaje: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Notificar a múltiples usuarios
     */
    public function notifyMultipleUsers(array $userIds, $data, $tipo = 'general', $sendEmail = false)
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($userIds as $userId) {
            $result = $this->sendCompleteNotification($userId, $data, $tipo, null, $sendEmail);
            $results[$userId] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }

        return [
            'success' => $errorCount === 0,
            'message' => "Notificaciones procesadas: {$successCount} exitosas, {$errorCount} fallidas",
            'summary' => [
                'total' => count($userIds),
                'success' => $successCount,
                'errors' => $errorCount
            ],
            'details' => $results
        ];
    }

    /**
     * Notificar según tipo de evento del sistema
     */
    public function notifyByEvent($event, $userId, $eventData = [])
    {
        $notifications = $this->getNotificationsByEvent($event, $eventData);
        
        if (empty($notifications)) {
            return [
                'success' => false,
                'message' => 'No hay configuración de notificación para el evento: ' . $event
            ];
        }

        $data = $notifications['data'];
        $tipo = $notifications['tipo'];
        $sendEmail = $notifications['sendEmail'] ?? false;
        $asunto = $notifications['asunto'] ?? null;

        return $this->sendCompleteNotification($userId, $data, $tipo, $asunto, $sendEmail);
    }

    /**
     * Configuración de notificaciones por evento
     */
    private function getNotificationsByEvent($event, $eventData = [])
    {
        $eventConfigs = [
            'pedido_creado' => [
                'tipo' => 'pedido',
                'sendEmail' => true,
                'asunto' => 'Pedido confirmado - Fresaterra',
                'data' => [
                    'mensaje' => 'Tu pedido #' . ($eventData['pedido_id'] ?? '') . ' ha sido confirmado y está siendo procesado.',
                    'pedido_id' => $eventData['pedido_id'] ?? null,
                    'estado' => 'confirmado',
                    'monto' => $eventData['monto'] ?? null
                ]
            ],
            'pedido_enviado' => [
                'tipo' => 'envio',
                'sendEmail' => true,
                'asunto' => 'Tu pedido está en camino - Fresaterra',
                'data' => [
                    'mensaje' => 'Tu pedido #' . ($eventData['pedido_id'] ?? '') . ' ha sido enviado y está en camino.',
                    'pedido_id' => $eventData['pedido_id'] ?? null,
                    'tracking' => $eventData['tracking'] ?? null,
                    'transportista' => $eventData['transportista'] ?? null
                ]
            ],
            'producto_disponible' => [
                'tipo' => 'producto',
                'sendEmail' => false,
                'data' => [
                    'mensaje' => 'El producto "' . ($eventData['producto_nombre'] ?? '') . '" ya está disponible.',
                    'producto_id' => $eventData['producto_id'] ?? null,
                    'producto_nombre' => $eventData['producto_nombre'] ?? null
                ]
            ],
            'promocion_nueva' => [
                'tipo' => 'promocion',
                'sendEmail' => true,
                'asunto' => '¡Nueva promoción disponible! - Fresaterra',
                'data' => [
                    'mensaje' => 'Nueva promoción: ' . ($eventData['descuento'] ?? '0') . '% de descuento en productos seleccionados.',
                    'descuento' => $eventData['descuento'] ?? null,
                    'productos' => $eventData['productos'] ?? []
                ]
            ],
            'inventario_bajo' => [
                'tipo' => 'inventario',
                'sendEmail' => false,
                'data' => [
                    'mensaje' => 'Stock bajo para el producto: ' . ($eventData['producto_nombre'] ?? ''),
                    'producto_id' => $eventData['producto_id'] ?? null,
                    'stock_actual' => $eventData['stock_actual'] ?? null
                ]
            ]
        ];

        return $eventConfigs[$event] ?? [];
    }
}

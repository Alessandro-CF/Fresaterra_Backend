<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Notificacion;
use App\Models\Mensaje;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class NotificacionController extends Controller
{
    // =====================================
    // MÉTODOS DE NOTIFICACIÓN INTERNA
    // =====================================

    /**
     * Enviar notificación tipo campanita (solo in-app)
     */
    private function sendInAppNotification($userId, $data, $tipo = 'general')
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Crear notificación en base de datos usando Laravel Notifications
            $notificationData = $this->formatCampanitaNotification($data, $tipo, $user);
            
            // Crear la notificación directamente en la base de datos
            $user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'App\\Notifications\\CampanitaNotification',
                'data' => $notificationData,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

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
    private function sendEmailNotification($userId, $data, $tipo = 'general', $asunto = null)
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                throw new \Exception('Usuario no encontrado');
            }

            // Enviar email usando Laravel Mail
            $mailMessage = $this->buildEmailMessage($data, $tipo, $asunto, $user);
            
            // Enviar el email
            $user->notify(new \Illuminate\Notifications\Messages\SimpleMessage($mailMessage));

            // También crear registro en base de datos
            $notificationData = $this->formatEmailNotification($data, $tipo, $asunto, $user);
            $user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'type' => 'App\\Notifications\\EmailNotification',
                'data' => $notificationData,
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

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
    private function sendCompleteNotification($userId, $data, $tipo = 'general', $asunto = null, $sendEmail = true)
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
    private function createNotificationFromMessage($userId, $mensajeId, $tipo = 'general', $sendEmail = false)
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
    private function notifyMultipleUsers(array $userIds, $data, $tipo = 'general', $sendEmail = false)
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
    private function notifyByEvent($event, $userId, $eventData = [])
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

    /**
     * Formatear datos para notificación campanita
     */
    private function formatCampanitaNotification($data, $tipo, $user)
    {
        return [
            'tipo' => $tipo,
            'mensaje' => $data['mensaje'] ?? '',
            'data' => $data,
            'fecha' => now()->toDateTimeString(),
            'usuario_id' => $user->id_usuario,
            'icon' => $this->getIconByType($tipo),
            'color' => $this->getColorByType($tipo)
        ];
    }

    /**
     * Formatear datos para notificación email
     */
    private function formatEmailNotification($data, $tipo, $asunto, $user)
    {
        return [
            'tipo' => $tipo,
            'mensaje' => $data['mensaje'] ?? '',
            'data' => $data,
            'fecha' => now()->toDateTimeString(),
            'usuario_id' => $user->id_usuario,
            'asunto' => $asunto ?? 'Nueva notificación de Fresaterra'
        ];
    }

    /**
     * Construir mensaje de email
     */
    private function buildEmailMessage($data, $tipo, $asunto, $user)
    {
        $mailMessage = (new MailMessage)
            ->subject($asunto ?? 'Nueva notificación de Fresaterra')
            ->greeting('¡Hola ' . $user->nombre . '!')
            ->line($data['mensaje'] ?? 'Tienes una nueva notificación.')
            ->line('Detalles:');

        // Agregar información adicional según el tipo
        switch ($tipo) {
            case 'pedido':
                $mailMessage->line('Tipo: Actualización de pedido')
                           ->line('Estado: ' . ($data['estado'] ?? 'Pendiente'))
                           ->action('Ver pedido', url('/api/v1/orders/' . ($data['pedido_id'] ?? '')));
                break;
            
            case 'producto':
                $mailMessage->line('Tipo: Notificación de producto')
                           ->line('Producto: ' . ($data['producto_nombre'] ?? ''))
                           ->action('Ver producto', url('/api/v1/products/' . ($data['producto_id'] ?? '')));
                break;
            
            case 'promocion':
                $mailMessage->line('Tipo: Promoción especial');
                if (isset($data['descuento'])) {
                    $mailMessage->line('Oferta: ' . $data['descuento'] . '% de descuento');
                }
                $mailMessage->action('Ver promociones', url('/api/v1/products'));
                break;
            
            case 'registro':
                $mailMessage->line('Tipo: Confirmación de registro');
                if (isset($data['fecha_registro'])) {
                    $mailMessage->line('Fecha de registro: ' . $data['fecha_registro']);
                }
                if (isset($data['mensaje_bienvenida'])) {
                    $mailMessage->line($data['mensaje_bienvenida']);
                }
                if (isset($data['action_url'])) {
                    $mailMessage->action('Iniciar sesión', $data['action_url']);
                } else {
                    $mailMessage->action('Ir a la aplicación', url('/'));
                }
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
     * Obtener icono según tipo de notificación
     */
    private function getIconByType($tipo): string
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
     * Obtener color según tipo de notificación
     */
    private function getColorByType($tipo): string
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

    // =====================================
    // MÉTODOS PÚBLICOS DE API
    // =====================================

    /**
     * Obtener notificaciones del usuario autenticado
     * GET /api/v1/me/notificaciones
     */
    public function getUserNotifications(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Notificacion::where('usuarios_id_usuario', $user->id_usuario)
                ->with(['mensaje' => function ($query) {
                    $query->select('id_mensaje', 'tipo', 'contenido', 'asunto');
                }])
                ->select('id_notificacion', 'estado', 'fecha_creacion', 'mensajes_id_mensaje', 'read_at', 'type', 'data')
                ->orderBy('fecha_creacion', 'desc');

            // Filtros opcionales
            if ($request->has('unread_only') && $request->boolean('unread_only')) {
                $query->whereNull('read_at');
            }

            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            // Paginación
            $perPage = $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);
            
            // Formatear la respuesta según el formato solicitado
            $formattedNotifications = $notifications->getCollection()->map(function ($notification) {
                return [
                    'id_notificacion' => $notification->id_notificacion,
                    'estado' => $notification->read_at ? 'leida' : 'no_leida',
                    'fecha_creacion' => $notification->fecha_creacion->toISOString(),
                    'mensaje' => [
                        'tipo' => $notification->data['tipo_mensaje'] ?? $notification->mensaje->tipo ?? $notification->type ?? 'general',
                        'contenido' => $notification->data['contenido_mensaje'] ?? $notification->mensaje->contenido ?? 'Sin contenido'
                    ]
                ];
            });

            return response()->json([
                'total' => $notifications->total(),
                'datos' => $formattedNotifications
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones del usuario: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificación como leída/no leída
     * PATCH /api/v1/me/notificaciones/{id_notificacion}
     */
    public function updateUserNotificationStatus(Request $request, $id_notificacion)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|string|in:leida,no_leida'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $user = Auth::user();
            
            $notification = Notificacion::where('id_notificacion', $id_notificacion)
                ->where('usuarios_id_usuario', $user->id_usuario)
                ->first();

            if (!$notification) {
                return response()->json([
                    'message' => 'Notificación no encontrada o no pertenece al usuario'
                ], 404);
            }

            if ($request->estado == 'leida') {
                $notification->markAsRead();
            } else {
                $notification->markAsUnread();
            }

            return response()->json([
                'mensaje' => 'Estado de notificación actualizado',
                'id_notificacion' => $notification->id_notificacion,
                'estado' => $notification->read_at ? 'leida' : 'no_leida'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar estado de notificación: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificación del usuario
     * DELETE /api/v1/me/notificaciones/{id_notificacion}
     */
    public function deleteUserNotification($id_notificacion)
    {
        try {
            $user = Auth::user();
            
            $notification = Notificacion::where('id_notificacion', $id_notificacion)
                ->where('usuarios_id_usuario', $user->id_usuario)
                ->first();

            if (!$notification) {
                return response()->json([
                    'message' => 'Notificación no encontrada o no pertenece al usuario'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'mensaje' => 'Notificación eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar notificación: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación desde administrador (con opción de email)
     * POST /api/v1/admin/notificaciones
     */
    public function sendAdminNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_usuario' => 'sometimes|required_without:todos_los_usuarios|exists:users,id_usuario',
            'todos_los_usuarios' => 'sometimes|required_without:id_usuario|boolean',
            'tipo_mensaje' => 'required|string|in:general,promocion,sistema,urgente,novedad',
            'contenido_mensaje' => 'required|string|max:1000',
            'enviar_email' => 'sometimes|boolean',
            'asunto_email' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        try {
            $userIds = [];
            
            if ($request->get('todos_los_usuarios')) {
                $userIds = User::pluck('id_usuario')->toArray();
            } elseif ($request->has('id_usuario')) {
                $userIds = [$request->id_usuario];
            }

            if (empty($userIds)) {
                return response()->json([
                    'message' => 'No se encontraron usuarios destinatarios'
                ], 400);
            }

            $data = [
                'mensaje' => $request->contenido_mensaje,
                'fecha_notificacion' => now()->format('d/m/Y H:i:s'),
                'tipo_notificacion' => ucfirst($request->tipo_mensaje)
            ];

            $enviarEmail = $request->get('enviar_email', false);
            $asunto = $request->asunto_email ?? 'Notificación de Fresaterra: ' . ucfirst($request->tipo_mensaje);

            $result = $this->notifyMultipleUsers($userIds, $data, $request->tipo_mensaje, $enviarEmail);

            return response()->json([
                'mensaje' => $result['message'],
                'success' => $result['success'],
                'total_enviadas' => $result['summary']['total'],
                'exitosas' => $result['summary']['success'],
                'errores' => $result['summary']['errors'],
                'emails_enviados' => $enviarEmail ? $result['summary']['success'] : 0
            ], $result['success'] ? 201 : 207);

        } catch (\Exception $e) {
            Log::error('Error al enviar notificaciones administrativas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al enviar notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todas las notificaciones (Admin)
     * GET /api/v1/admin/notificaciones
     */
    public function getAllAdminNotifications(Request $request)
    {
        try {
            $query = Notificacion::with([
                'usuario' => function ($query) {
                    $query->select('id_usuario', 'nombre', 'email');
                },
                'mensaje' => function ($query) {
                    $query->select('id_mensaje', 'tipo', 'contenido', 'asunto');
                }
            ])
            ->select('id_notificacion', 'estado', 'fecha_creacion', 'usuarios_id_usuario', 'mensajes_id_mensaje', 'read_at', 'type', 'data')
            ->orderBy('fecha_creacion', 'desc');

            // Filtros opcionales
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }
            
            if ($request->has('estado') && in_array($request->estado, ['leida', 'no_leida'])) {
                if ($request->estado == 'leida') {
                    $query->whereNotNull('read_at');
                } else {
                    $query->whereNull('read_at');
                }
            }
            
            if ($request->has('user_id') && $request->user_id) {
                $query->where('usuarios_id_usuario', $request->user_id);
            }

            // Paginación
            $perPage = $request->get('per_page', 50);
            $notifications = $query->paginate($perPage);

            // Formatear la respuesta según el formato solicitado
            $formattedNotifications = $notifications->getCollection()->map(function ($notification) {
                return [
                    'id_notificacion' => $notification->id_notificacion,
                    'estado' => $notification->read_at ? 'leida' : 'no_leida',
                    'fecha_creacion' => $notification->fecha_creacion->toISOString(),
                    'usuario' => $notification->usuario ? [
                        'id_usuario' => $notification->usuario->id_usuario,
                        'nombre' => $notification->usuario->nombre
                    ] : null,
                    'mensaje' => [
                        'tipo' => $notification->data['tipo_mensaje'] ?? $notification->mensaje->tipo ?? $notification->type ?? 'general',
                        'contenido' => $notification->data['contenido_mensaje'] ?? $notification->mensaje->contenido ?? 'Sin contenido'
                    ]
                ];
            });

            return response()->json([
                'total' => $notifications->total(),
                'datos' => $formattedNotifications
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones (admin): ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificación (Admin)
     * DELETE /api/v1/admin/notificaciones/{id_notificacion}
     */
    public function deleteAdminNotification($id_notificacion)
    {
        try {
            $notification = Notificacion::find($id_notificacion);

            if (!$notification) {
                return response()->json([
                    'message' => 'Notificación no encontrada'
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'mensaje' => 'Notificación eliminada permanentemente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar notificación (admin): ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================
    // MÉTODOS ADICIONALES PARA EMAILS
    // =====================================

    /**
     * Enviar email de prueba (para verificar configuración)
     * POST /api/v1/admin/emails/test
     */
    public function sendTestEmail()
    {
        try {
            // Buscar un usuario administrador o usar el usuario actual
            $user = Auth::user();
            if (!$user) {
                // Si no hay usuario autenticado, buscar el primer administrador
                $user = User::where('roles_id_rol', 1)->first();
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró un usuario válido para enviar el correo de prueba'
                    ], 400);
                }
            }

            $data = [
                'mensaje' => '¡Este es un correo de prueba para verificar que el sistema de correos de Fresaterra está funcionando correctamente!',
                'fecha' => now()->format('d/m/Y H:i:s'),
                'sistema' => 'API Fresaterra v1.0',
                'email_configurado' => 'fresaterra@gmail.com',
                'servidor_smtp' => 'smtp.gmail.com'
            ];

            $result = $this->sendEmailNotification($user->id_usuario, $data, 'general', 'Correo de prueba - Fresaterra');

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            Log::error('Error al enviar email de prueba: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar correo de confirmación de registro a un usuario específico
     * POST /api/v1/admin/emails/registration-confirmation
     */
    public function sendRegistrationConfirmation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $user = User::findOrFail($request->user_id);

            $emailData = [
                'mensaje' => '¡Gracias por registrarte en Fresaterra! Tu cuenta ha sido creada exitosamente.',
                'email_usuario' => $user->email,
                'fecha_registro' => $user->created_at->format('d/m/Y H:i:s'),
                'mensaje_bienvenida' => 'Ahora puedes disfrutar de nuestros productos frescos y naturales.',
                'action_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/login'
            ];

            $result = $this->sendEmailNotification($user->id_usuario, $emailData, 'registro', '¡Bienvenido a Fresaterra! - Confirmación de Registro');

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Correo de confirmación de registro enviado exitosamente' : $result['message'],
                'user_email' => $user->email,
                'user_name' => $user->nombre ?? $user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Error al enviar correo de confirmación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar correo de confirmación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar correos de confirmación a múltiples usuarios
     * POST /api/v1/admin/emails/registration-confirmation/multiple
     */
    public function sendMultipleRegistrationConfirmations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id_usuario'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $emailData = [
                'mensaje' => '¡Gracias por registrarte en Fresaterra! Tu cuenta ha sido creada exitosamente.',
                'mensaje_bienvenida' => 'Ahora puedes disfrutar de nuestros productos frescos y naturales.',
                'action_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/login'
            ];

            $result = $this->notifyMultipleUsers($request->user_ids, $emailData, 'registro', true);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'summary' => $result['summary'],
                'details' => $result['details']
            ], $result['success'] ? 200 : 207);

        } catch (\Exception $e) {
            Log::error('Error general en envío múltiple: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error general al enviar correos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar correo de confirmación a todos los usuarios registrados
     * POST /api/v1/admin/emails/registration-confirmation/all
     */
    public function sendConfirmationToAllUsers()
    {
        try {
            $users = User::all();

            if ($users->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay usuarios registrados para enviar correos'
                ], 404);
            }

            $userIds = $users->pluck('id_usuario')->toArray();
            $request = new Request(['user_ids' => $userIds]);
            return $this->sendMultipleRegistrationConfirmations($request);

        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios para envío masivo: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // =====================================
    // MÉTODOS PÚBLICOS PARA USO EXTERNO
    // =====================================

    /**
     * Enviar notificación por evento (público para otros controladores)
     * POST /api/v1/notifications/event
     */
    public function sendEventNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event' => 'required|string',
            'user_id' => 'required|exists:users,id_usuario',
            'event_data' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->notifyByEvent($request->event, $request->user_id, $request->event_data ?? []);
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación por evento: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación personalizada (público para otros controladores)
     * POST /api/v1/notifications/custom
     */
    public function sendCustomNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario',
            'data' => 'required|array',
            'data.mensaje' => 'required|string',
            'tipo' => 'sometimes|string',
            'send_email' => 'sometimes|boolean',
            'asunto' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->sendCompleteNotification(
                $request->user_id,
                $request->data,
                $request->tipo ?? 'general',
                $request->asunto ?? null,
                $request->send_email ?? false
            );
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error al enviar notificación personalizada: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar notificación',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método estático para usar desde otros controladores
     */
    public static function notify($userId, $data, $tipo = 'general', $sendEmail = false, $asunto = null)
    {
        $controller = new self();
        return $controller->sendCompleteNotification($userId, $data, $tipo, $asunto, $sendEmail);
    }

    /**
     * Método estático para notificar por evento desde otros controladores
     */
    public static function notifyEvent($event, $userId, $eventData = [])
    {
        $controller = new self();
        return $controller->notifyByEvent($event, $userId, $eventData);
    }

    /**
     * Obtener lista de usuarios registrados (para verificación)
     * GET /api/v1/admin/users/registered
     */
    public function getRegisteredUsers()
    {
        try {
            $users = User::select('id_usuario', 'nombre', 'apellidos', 'email', 'telefono', 'created_at')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Lista de usuarios obtenida exitosamente',
                'total_usuarios' => $users->count(),
                'usuarios' => $users
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener usuarios: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
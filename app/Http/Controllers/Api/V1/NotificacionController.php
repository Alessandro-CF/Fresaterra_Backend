<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Notificacion;
use App\Models\Mensaje;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    /**
     * Obtener notificaciones del usuario autenticado
     * GET /api/v1/me/notificaciones
     */
    public function getUserNotifications(Request $request)
    {
        try {
            $user = Auth::user();
            
            $query = Notificacion::where('usuarios_id_usuario', $user->id_usuario)
                ->where('type', 'campanita') // Solo mostrar notificaciones de campanita
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
     * Obtener solo las notificaciones no leídas del usuario autenticado
     * GET /api/v1/me/notificaciones/unread
     */
    public function getUnreadNotifications(Request $request)
    {
        try {
            $user = Auth::user();
            
            $notifications = Notificacion::where('usuarios_id_usuario', $user->id_usuario)
                ->whereNull('read_at')
                ->where('type', 'campanita') // Solo mostrar notificaciones de campanita
                ->with(['mensaje' => function ($query) {
                    $query->select('id_mensaje', 'tipo', 'contenido', 'asunto');
                }])
                ->select('id_notificacion', 'estado', 'fecha_creacion', 'mensajes_id_mensaje', 'read_at', 'type', 'data')
                ->orderBy('fecha_creacion', 'desc')
                ->get();

            // Formatear la respuesta
            $formattedNotifications = $notifications->map(function ($notification) {
                return [
                    'id_notificacion' => $notification->id_notificacion,
                    'estado' => 'no_leida',
                    'fecha_creacion' => $notification->fecha_creacion->toISOString(),
                    'read_at' => null,
                    'type' => $notification->type,
                    'data' => $notification->data,
                    'mensaje' => $notification->mensaje ? [
                        'id_mensaje' => $notification->mensaje->id_mensaje,
                        'tipo' => $notification->mensaje->tipo,
                        'contenido' => $notification->mensaje->contenido,
                        'asunto' => $notification->mensaje->asunto,
                    ] : null
                ];
            });

            return response()->json([
                'success' => true,
                'count' => $formattedNotifications->count(),
                'datos' => $formattedNotifications
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones no leídas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notificaciones no leídas',
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
            'tipo_mensaje' => 'required|string|in:general,promocion,sistema,urgente,novedad,email,campanita',
            'asunto' => 'required|string|max:255',
            'contenido_mensaje' => 'required|string|max:1000',
            'prioridad' => 'sometimes|string|in:baja,normal,alta,urgente'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }
        try {
            $userIds = collect();
            
            if ($request->get('todos_los_usuarios')) {
                $userIds = User::where('estado', 'activo')->pluck('id_usuario');
            } elseif ($request->has('id_usuario')) {
                $userIds->push($request->id_usuario);
            }

            if ($userIds->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron usuarios destinatarios'
                ], 400);
            }

            $datos = [
                'prioridad' => $request->get('prioridad', 'normal'),
                'admin_id' => Auth::id(),
                'fecha_envio' => now()->toDateTimeString()
            ];

            $notificaciones = NotificationService::enviarAMultiplesUsuarios(
                $userIds->toArray(),
                $request->tipo_mensaje,
                $request->asunto,
                $request->contenido_mensaje,
                $datos
            );
            return response()->json([
                'mensaje' => 'Notificación(es) enviada(s) exitosamente',
                'total_enviadas' => count($notificaciones),
                'usuarios_notificados' => $userIds->count()
            ], 201);

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

    /**
     * Enviar email de prueba usando el servicio personalizado
     * POST /api/v1/admin/emails/test
     */
    public function sendTestEmail()
    {
        try {
            // Usar el usuario autenticado o buscar un administrador
            $user = Auth::user();
            if (!$user) {
                $user = User::where('roles_id_rol', 1)->first();
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró un usuario válido para enviar el correo de prueba'
                    ], 400);
                }
            }

            $contenido = '¡Este es un correo de prueba para verificar que el sistema de correos de Fresaterra está funcionando correctamente!';
            $datos = [
                'fecha' => now()->format('d/m/Y H:i:s'),
                'sistema' => 'API Fresaterra v1.0',
                'servidor_smtp' => config('mail.mailers.smtp.host', 'No configurado')
            ];

            $notificacion = NotificationService::enviarEmail(
                $user->id_usuario,
                'Correo de prueba - Fresaterra',
                $contenido,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Email de prueba enviado correctamente',
                'notificacion_id' => $notificacion->id_notificacion
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

            $contenido = '¡Gracias por registrarte en Fresaterra! Tu cuenta ha sido creada exitosamente. Ahora puedes disfrutar de nuestros productos frescos y naturales.';
            $datos = [
                'email_usuario' => $user->email,
                'fecha_registro' => $user->created_at->format('d/m/Y H:i:s'),
                'mensaje_bienvenida' => 'Ahora puedes disfrutar de nuestros productos frescos y naturales.',
                'action_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/login',
                'tipo' => 'registro'
            ];

            $notificacion = NotificationService::enviarEmail(
                $user->id_usuario,
                '¡Bienvenido a Fresaterra! - Confirmación de Registro',
                $contenido,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Correo de confirmación de registro enviado exitosamente',
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
            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($request->user_ids as $userId) {
                try {
                    $user = User::findOrFail($userId);

                    $contenido = '¡Gracias por registrarte en Fresaterra! Tu cuenta ha sido creada exitosamente. Ahora puedes disfrutar de nuestros productos frescos y naturales.';
                    $datos = [
                        'email_usuario' => $user->email,
                        'fecha_registro' => $user->created_at->format('d/m/Y H:i:s'),
                        'mensaje_bienvenida' => 'Ahora puedes disfrutar de nuestros productos frescos y naturales.',
                        'action_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/login',
                        'tipo' => 'registro'
                    ];

                    $notificacion = NotificationService::enviarEmail(
                        $user->id_usuario,
                        '¡Bienvenido a Fresaterra! - Confirmación de Registro',
                        $contenido,
                        $datos
                    );

                    $results[] = [
                        'user_id' => $userId,
                        'email' => $user->email,
                        'status' => 'enviado',
                        'message' => 'Correo enviado exitosamente'
                    ];
                    $successCount++;

                } catch (\Exception $e) {
                    Log::error("Error enviando correo a usuario {$userId}: " . $e->getMessage());
                    $results[] = [
                        'user_id' => $userId,
                        'status' => 'error',
                        'message' => 'Error al enviar correo: ' . $e->getMessage()
                    ];
                    $errorCount++;
                }
            }

            return response()->json([
                'success' => $successCount > 0,
                'message' => "Proceso completado. {$successCount} correos enviados, {$errorCount} errores.",
                'summary' => [
                    'total_usuarios' => count($request->user_ids),
                    'enviados_exitosamente' => $successCount,
                    'errores' => $errorCount
                ],
                'details' => $results
            ], $errorCount > 0 ? 207 : 200);

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

    // =====================================
    // MÉTODOS DE API PARA NOTIFICATION SERVICE
    // =====================================

    /**
     * Enviar notificación completa via API
     * POST /api/v1/admin/notificaciones/send-complete
     */
    public function apiSendCompleteNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario',
            'data' => 'required|array',
            'data.mensaje' => 'required|string',
            'tipo' => 'sometimes|string',
            'asunto' => 'sometimes|string',
            'send_email' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->sendCompleteNotification(
                $request->user_id,
                $request->data,
                $request->get('tipo', 'general'),
                $request->get('asunto'),
                $request->get('send_email', true)
            );

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Error en API sendCompleteNotification: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear notificación desde mensaje via API
     * POST /api/v1/admin/notificaciones/from-message
     */
    public function apiCreateNotificationFromMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario',
            'mensaje_id' => 'required|exists:mensajes,id_mensaje',
            'tipo' => 'sometimes|string',
            'send_email' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->createNotificationFromMessage(
                $request->user_id,
                $request->mensaje_id,
                $request->get('tipo', 'general'),
                $request->get('send_email', false)
            );

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Error en API createNotificationFromMessage: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear notificación desde mensaje: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notificar múltiples usuarios via API
     * POST /api/v1/admin/notificaciones/notify-multiple
     */
    public function apiNotifyMultipleUsers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id_usuario',
            'data' => 'required|array',
            'data.mensaje' => 'required|string',
            'tipo' => 'sometimes|string',
            'send_email' => 'sometimes|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->notifyMultipleUsers(
                $request->user_ids,
                $request->data,
                $request->get('tipo', 'general'),
                $request->get('send_email', false)
            );

            return response()->json($result, $result['success'] ? 200 : 207);

        } catch (\Exception $e) {
            Log::error('Error en API notifyMultipleUsers: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al notificar múltiples usuarios: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notificar por evento via API
     * POST /api/v1/admin/notificaciones/notify-by-event
     */
    public function apiNotifyByEvent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'event' => 'required|string|in:pedido_creado,pedido_enviado,producto_disponible,promocion_nueva,inventario_bajo',
            'user_id' => 'required|exists:users,id_usuario',
            'event_data' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->notifyByEvent(
                $request->event,
                $request->user_id,
                $request->get('event_data', [])
            );

            return response()->json($result, $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Error en API notifyByEvent: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al notificar por evento: ' . $e->getMessage()
            ], 500);
        }
    }

    // =====================================
    // MÉTODOS DEL NOTIFICATION SERVICE
    // =====================================

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

            // Preparar contenido y datos para NotificationService
            $contenido = $data['mensaje'] ?? 'Notificación nueva';
            $asunto = $data['asunto'] ?? 'Notificación';
            
            // Enviar notificación campanita usando NotificationService
            $notificacion = NotificationService::enviarCampanita(
                $userId,
                $asunto,
                $contenido,
                $data
            );

            return [
                'success' => true,
                'message' => 'Notificación in-app enviada correctamente',
                'type' => 'campanita',
                'notification_id' => $notificacion->id_notificacion
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

            // Preparar contenido y datos para NotificationService
            $contenido = $data['mensaje'] ?? 'Notificación nueva';
            $asuntoFinal = $asunto ?? $data['asunto'] ?? 'Notificación de Fresaterra';
            
            // Enviar notificación por email usando NotificationService
            $notificacion = NotificationService::enviarEmail(
                $userId,
                $asuntoFinal,
                $contenido,
                $data
            );

            return [
                'success' => true,
                'message' => 'Notificación por email enviada correctamente',
                'type' => 'email',
                'notification_id' => $notificacion->id_notificacion
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

    /**
     * Enviar notificación por email usando servicio personalizado
     * POST /api/v1/notificaciones/enviar-email
     */
    public function enviarEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario',
            'asunto' => 'required|string|max:255',
            'contenido' => 'required|string',
            'prioridad' => 'sometimes|in:baja,normal,alta,urgente'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $datos = [
                'prioridad' => $request->prioridad ?? 'normal',
                'metadata' => $request->metadata ?? []
            ];

            $notificacion = NotificationService::enviarEmail(
                $request->user_id,
                $request->asunto,
                $request->contenido,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Notificación de email enviada correctamente',
                'data' => $notificacion->load(['usuario', 'mensaje'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error al enviar email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación campanita usando servicio personalizado
     * POST /api/v1/notificaciones/enviar-campanita
     */
    public function enviarCampanita(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario',
            'asunto' => 'required|string|max:255',
            'contenido' => 'required|string',
            'prioridad' => 'sometimes|in:baja,normal,alta,urgente'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $datos = [
                'prioridad' => $request->prioridad ?? 'normal',
                'metadata' => $request->metadata ?? []
            ];

            $notificacion = NotificationService::enviarCampanita(
                $request->user_id,
                $request->asunto,
                $request->contenido,
                $datos
            );

            return response()->json([
                'success' => true,
                'message' => 'Notificación campanita enviada correctamente',
                'data' => $notificacion->load(['usuario', 'mensaje'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error al enviar campanita: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar notificación directa usando servicio personalizado
     * POST /api/v1/notificaciones/enviar-directa
     */
    public function enviarDirecta(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario',
            'tipo' => 'required|string|max:50',
            'contenido' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $notificacion = NotificationService::enviarDirecta(
                $request->user_id,
                $request->tipo,
                $request->contenido,
                $request->metadata ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Notificación directa enviada correctamente',
                'data' => $notificacion->load('usuario')
            ]);

        } catch (\Exception $e) {
            Log::error('Error al enviar notificación directa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar la notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar notificación como leída usando servicio personalizado
     * PUT /api/v1/notificaciones/marcar-leida/{id}
     */
    public function marcarComoLeida($id)
    {
        try {
            $notificacion = NotificationService::marcarComoLeida($id);

            if (!$notificacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notificación no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída',
                'data' => $notificacion
            ]);

        } catch (\Exception $e) {
            Log::error('Error al marcar notificación como leída: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar la notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas usando servicio personalizado
     * PUT /api/v1/notificaciones/marcar-todas-leidas
     */
    public function marcarTodasComoLeidas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario requerido',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $updated = NotificationService::marcarTodasComoLeidas($request->user_id);

            return response()->json([
                'success' => true,
                'message' => "Se marcaron {$updated} notificaciones como leídas",
                'total_updated' => $updated
            ]);

        } catch (\Exception $e) {
            Log::error('Error al marcar todas las notificaciones como leídas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al marcar las notificaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas usando servicio personalizado
     * GET /api/v1/notificaciones/estadisticas
     */
    public function obtenerEstadisticas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario requerido',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $estadisticas = NotificationService::obtenerEstadisticas($request->user_id);

            return response()->json([
                'success' => true,
                'data' => $estadisticas
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener notificaciones no leídas de un usuario
     * GET /api/v1/notificaciones/no-leidas
     */
    public function obtenerNoLeidas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id_usuario'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'ID de usuario requerido',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $notificaciones = Notificacion::with(['usuario', 'mensaje'])
                ->where('usuarios_id_usuario', $request->user_id)
                ->whereNull('read_at')
                ->where('estado', 'activo')
                ->orderBy('fecha_creacion', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $notificaciones,
                'total' => $notificaciones->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener notificaciones no leídas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener notificaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar notificación (marcar como inactiva)
     * DELETE /api/v1/notificaciones/eliminar/{id}
     */
    public function eliminarNotificacion($id)
    {
        try {
            $notificacion = Notificacion::find($id);

            if (!$notificacion) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notificación no encontrada'
                ], 404);
            }

            $notificacion->estado = 'inactivo';
            $notificacion->save();

            return response()->json([
                'success' => true,
                'message' => 'Notificación eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar notificación: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la notificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar notificaciones de email (temporal)
     * DELETE /api/v1/me/notificaciones/clean-email
     */
    public function cleanEmailNotifications(Request $request)
    {
        try {
            $user = Auth::user();
            
            // Eliminar todas las notificaciones de tipo email del usuario
            $deletedCount = Notificacion::where('usuarios_id_usuario', $user->id_usuario)
                ->where('type', 'email')
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$deletedCount} notificaciones de email",
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error al limpiar notificaciones de email: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar notificaciones de email: ' . $e->getMessage()
            ], 500);
        }
    }
}
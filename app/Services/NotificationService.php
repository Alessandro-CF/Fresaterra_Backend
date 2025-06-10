<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Enviar notificación por email
     */
    public static function enviarEmail($userId, $asunto, $contenido, $datos = [])
    {
        try {
            // Crear el mensaje
            $mensaje = Mensaje::create([
                'tipo' => 'email',
                'asunto' => $asunto,
                'contenido' => $contenido,
                'estado' => 'activo',
                'prioridad' => $datos['prioridad'] ?? 'normal'
            ]);

            // Crear la notificación
            $notificacion = Notificacion::create([
                'uuid' => Str::uuid(),
                'type' => 'email',
                'estado' => 'activo',
                'data' => $datos,
                'usuarios_id_usuario' => $userId,
                'mensajes_id_mensaje' => $mensaje->id_mensaje
            ]);

            // Enviar email real si está configurado
            if (config('mail.default') !== 'log') {
                self::enviarEmailReal($userId, $asunto, $contenido, $datos);
            }

            return $notificacion;

        } catch (\Exception $e) {
            Log::error('Error enviando email: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar notificación campanita (en app)
     */
    public static function enviarCampanita($userId, $asunto, $contenido, $datos = [])
    {
        try {
            // Crear el mensaje
            $mensaje = Mensaje::create([
                'tipo' => 'campanita',
                'asunto' => $asunto,
                'contenido' => $contenido,
                'estado' => 'activo',
                'prioridad' => $datos['prioridad'] ?? 'normal'
            ]);

            // Crear la notificación
            $notificacion = Notificacion::create([
                'uuid' => Str::uuid(),
                'type' => 'campanita',
                'estado' => 'activo',
                'data' => $datos,
                'usuarios_id_usuario' => $userId,
                'mensajes_id_mensaje' => $mensaje->id_mensaje
            ]);

            return $notificacion;

        } catch (\Exception $e) {
            Log::error('Error enviando campanita: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar notificación directa (sin mensaje asociado)
     */
    public static function enviarDirecta($userId, $tipo, $contenido, $datos = [])
    {
        try {
            // Crear notificación directa sin mensaje asociado
            $notificacion = Notificacion::create([
                'uuid' => Str::uuid(),
                'type' => $tipo,
                'estado' => 'activo',
                'data' => array_merge($datos, ['contenido' => $contenido]),
                'usuarios_id_usuario' => $userId,
                'mensajes_id_mensaje' => null
            ]);

            return $notificacion;

        } catch (\Exception $e) {
            Log::error('Error enviando notificación directa: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enviar notificación a múltiples usuarios
     */
    public static function enviarAMultiplesUsuarios($userIds, $tipo, $asunto, $contenido, $datos = [])
    {
        $notificaciones = [];
        
        foreach ($userIds as $userId) {
            try {
                if ($tipo === 'email') {
                    $notificaciones[] = self::enviarEmail($userId, $asunto, $contenido, $datos);
                } elseif ($tipo === 'campanita') {
                    $notificaciones[] = self::enviarCampanita($userId, $asunto, $contenido, $datos);
                } else {
                    $notificaciones[] = self::enviarDirecta($userId, $tipo, $contenido, $datos);
                }
            } catch (\Exception $e) {
                Log::error("Error enviando notificación a usuario {$userId}: " . $e->getMessage());
            }
        }

        return $notificaciones;
    }

    /**
     * Enviar notificación a todos los usuarios
     */
    public static function enviarATodosLosUsuarios($tipo, $asunto, $contenido, $datos = [])
    {
        $users = User::where('estado', 'activo')->pluck('id_usuario')->toArray();
        return self::enviarAMultiplesUsuarios($users, $tipo, $asunto, $contenido, $datos);
    }

    /**
     * Marcar notificación como leída
     */
    public static function marcarComoLeida($notificacionId)
    {
        $notificacion = Notificacion::find($notificacionId);
        if ($notificacion && !$notificacion->read_at) {
            $notificacion->read_at = now();
            $notificacion->save();
        }
        return $notificacion;
    }

    /**
     * Marcar todas las notificaciones de un usuario como leídas
     */
    public static function marcarTodasComoLeidas($userId)
    {
        return Notificacion::where('usuarios_id_usuario', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * Obtener estadísticas de notificaciones de un usuario
     */
    public static function obtenerEstadisticas($userId)
    {
        $total = Notificacion::where('usuarios_id_usuario', $userId)
            ->where('estado', 'activo')
            ->count();

        $noLeidas = Notificacion::where('usuarios_id_usuario', $userId)
            ->where('estado', 'activo')
            ->whereNull('read_at')
            ->count();

        $leidas = $total - $noLeidas;

        $porTipo = Notificacion::where('usuarios_id_usuario', $userId)
            ->where('estado', 'activo')
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->get();

        return [
            'total' => $total,
            'leidas' => $leidas,
            'no_leidas' => $noLeidas,
            'por_tipo' => $porTipo
        ];
    }

    /**
     * Enviar email real usando el sistema de correo
     */
    private static function enviarEmailReal($userId, $asunto, $contenido, $datos = [])
    {
        $user = User::find($userId);
        if (!$user || !$user->email) {
            throw new \Exception('Usuario no encontrado o sin email');
        }

        $emailData = [
            'subject' => $asunto,
            'user_name' => $user->nombre,
            'content' => $contenido,
            'data' => $datos
        ];

        Mail::send('emails.notification', $emailData, function ($message) use ($user, $asunto) {
            $message->to($user->email, $user->nombre)
                    ->subject($asunto);
        });
    }
}

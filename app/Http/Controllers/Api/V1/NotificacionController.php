<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Notificacion;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    /**
     * Mostrar una lista de notificaciones.
     */
    public function index()
    {
        $notificaciones = Notificacion::with(['mensaje', 'usuario'])->get();
        
        // Si la petición espera JSON, devolver JSON
        if (request()->wantsJson() || request()->ajax() || request()->is('api/*')) {
            return response()->json($notificaciones);
        }
        
        // Si es una petición del navegador, mostrar en formato HTML
        return response()->view('notificaciones.index', ['notificaciones' => $notificaciones]);
    }
    
    /**
     * Método alternativo que siempre devuelve JSON
     */
    public function indexJson()
    {
        return response()->json(Notificacion::with(['mensaje', 'usuario'])->get());
    }

    /**
     * Almacenar una nueva notificación.
     */     public function store(Request $request)
     {
         $request->validate([
             'estado' => 'required|string|max:45',
             'fecha_creacion' => 'nullable|date',
             'mensajes_id_mensaje' => 'required|exists:mensajes,id_mensaje',
             'usuarios_id_usuario' => 'required|exists:users,id_usuario',
         ]);

         $notificacion = Notificacion::create($request->all());
        return response()->json($notificacion, 201);
    }

    /**
     * Mostrar una notificación específica.
     */
    public function show(Notificacion $notificacion)
    {
        return response()->json($notificacion->load(['mensaje', 'usuario']));
    }

    /**
     * Actualizar una notificación existente.
     */
    public function update(Request $request, Notificacion $notificacion)
    {
        $request->validate([
            'estado' => 'string|max:45',
            'fecha_creacion' => 'nullable|date',
            'mensajes_id_mensaje' => 'exists:mensajes,id_mensaje',
            'usuarios_id_usuario' => 'exists:users,id_usuario',
        ]);

        $notificacion->update($request->all());
        return response()->json($notificacion);
    }

    /**
     * Eliminar una notificación.
     */
    public function destroy(Notificacion $notificacion)
    {
        $notificacion->delete();
        return response()->json(null, 204);
    }
    
    /**
     * Obtener notificaciones del usuario autenticado
     */
    public function getUserNotifications()
    {
        // Si no hay usuario autenticado, devolvemos un error
        if (!Auth::check()) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        
        $user = Auth::user();
        $notificaciones = Notificacion::with(['mensaje'])
            ->where('usuarios_id_usuario', $user->id_usuario)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($notificaciones);
    }
    
    /**
     * Obtener notificaciones no leídas del usuario autenticado
     */
    public function getUnreadNotifications()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        
        $user = Auth::user();
        $notificaciones = Notificacion::with(['mensaje'])
            ->where('usuarios_id_usuario', $user->id_usuario)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($notificaciones);
    }
    
    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        
        $notificacion = Notificacion::where('id_notificacion', $id)
            ->where('usuarios_id_usuario', Auth::id())
            ->first();
            
        if (!$notificacion) {
            return response()->json(['error' => 'Notificación no encontrada'], 404);
        }
        
        $notificacion->markAsRead();
        
        return response()->json(['message' => 'Notificación marcada como leída']);
    }
    
    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead()
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Usuario no autenticado'], 401);
        }
        
        Notificacion::where('usuarios_id_usuario', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
            
        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas']);
    }
    
    /**
     * Crear notificación usando mensaje existente
     */
    public function notificarConMensaje(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id_usuario',
            'mensaje_id' => 'required|exists:mensajes,id_mensaje',
            'tipo' => 'required|string'
        ]);
        
        // Buscar mensaje
        $mensaje = Mensaje::find($request->mensaje_id);
        if (!$mensaje) {
            return response()->json(['error' => 'Mensaje no encontrado'], 404);
        }
        
        // Crear notificación
        $notificacion = new Notificacion();
        $notificacion->usuarios_id_usuario = $request->user_id;
        $notificacion->mensajes_id_mensaje = $request->mensaje_id;
        $notificacion->estado = 'sin_leer';
        $notificacion->type = 'App\\Notifications\\CampanitaNotification';
        $notificacion->data = [
            'mensaje' => $mensaje->contenido,
            'tipo' => $request->tipo,
            'fecha' => now()->toDateTimeString()
        ];
        $notificacion->save();
        
        return response()->json([
            'message' => 'Notificación enviada',
            'notificacion' => $notificacion
        ], 201);
    }
}

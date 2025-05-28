<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
    /***public function destroy(Notificacion $notificacion)
    {
        $notificacion->delete();
        return response()->json(null, 204);
    }*/
}

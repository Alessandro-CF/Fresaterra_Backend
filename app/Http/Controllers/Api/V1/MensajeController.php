<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Mensaje;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class MensajeController extends Controller
{
    /**
     * Mostrar una lista de mensajes.
     */
    public function index()
    {
        try {
            $mensajes = Mensaje::orderBy('created_at', 'desc')->get();
            return response()->json($mensajes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener mensajes'], 500);
        }
    }

    /**
     * Almacenar un nuevo mensaje.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'required|string|max:100',
            'contenido' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $mensaje = Mensaje::create([
                'tipo' => $request->tipo,
                'contenido' => $request->contenido
            ]);

            return response()->json($mensaje, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear mensaje'], 500);
        }
    }

    /**
     * Mostrar un mensaje específico.
     */
    public function show($id)
    {
        try {
            $mensaje = Mensaje::findOrFail($id);
            return response()->json($mensaje);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Mensaje no encontrado'], 404);
        }
    }

    /**
     * Actualizar un mensaje existente.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'tipo' => 'string|max:100',
            'contenido' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $mensaje = Mensaje::findOrFail($id);
            $mensaje->update($request->only(['tipo', 'contenido']));
            
            return response()->json($mensaje);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar mensaje'], 500);
        }
    }

    /**
     * Eliminar un mensaje.
     */
    public function destroy($id)
    {
        try {
            $mensaje = Mensaje::findOrFail($id);
            
            // Verificar si el mensaje tiene notificaciones asociadas
            if ($mensaje->notificaciones()->count() > 0) {
                return response()->json([
                    'error' => 'No se puede eliminar el mensaje porque tiene notificaciones asociadas'
                ], 400);
            }
            
            $mensaje->delete();
            return response()->json(['message' => 'Mensaje eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar mensaje'], 500);
        }
    }

    /**
     * Obtener mensajes por tipo
     */
    public function getByType($tipo)
    {
        try {
            $mensajes = Mensaje::where('tipo', $tipo)
                              ->orderBy('created_at', 'desc')
                              ->get();
            
            return response()->json($mensajes);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener mensajes por tipo'], 500);
        }
    }

    /**
     * Obtener tipos de mensajes disponibles
     */
    public function getTypes()
    {
        try {
            $tipos = Mensaje::distinct('tipo')
                           ->pluck('tipo')
                           ->sort()
                           ->values();
            
            return response()->json($tipos);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener tipos de mensajes'], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Comentario;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ComentarioController extends Controller
{
    public function index()
    {
        $comentarios = Comentario::with('usuario')->get();
        
        // Si la petición espera JSON, devolver JSON
        if (request()->wantsJson() || request()->ajax() || request()->is('api/*')) {
            return response()->json($comentarios);
        }
        
        // Si es una petición del navegador, mostrar en formato HTML
        return response()->view('comentarios.index', ['comentarios' => $comentarios]);
    }
    
    /**
     * Método alternativo que siempre devuelve JSON
     */
    public function indexJson()
    {
        return response()->json(Comentario::with('usuario')->get());
    }

    public function store(Request $request)
    {        $validated = $request->validate([
            'calificacion' => 'required|integer|min:1|max:5',
            'contenido' => 'required|string',
            'usuarios_id_usuario' => 'required|exists:users,id_usuario',
        ]);
        
        // Añadir fecha de creación
        $validated['fecha_creacion'] = now();

        $comentario = Comentario::create($validated);
        return response()->json($comentario, 201);
    }

    public function show($id)
    {
        // Si el ID es 'undefined' o inválido, devolver un error apropiado
        if ($id === 'undefined' || !is_numeric($id)) {
            return response()->json(['error' => 'ID de comentario inválido'], 400);
        }
        
        try {
            $comentario = Comentario::with('usuario')->findOrFail($id);
            return response()->json($comentario);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Comentario no encontrado'], 404);
        }
    }

    public function destroy($id)
    {
        Comentario::destroy($id);
        return response()->json(['mensaje' => 'Comentario eliminado']);
    }
    
    public function update(Request $request, $id)
    {
        // Si el ID es 'undefined' o inválido, devolver un error apropiado
        if ($id === 'undefined' || !is_numeric($id)) {
            return response()->json(['error' => 'ID de comentario inválido'], 400);
        }
        
        try {
            $comentario = Comentario::findOrFail($id);
            
            $validated = $request->validate([
                'calificacion' => 'sometimes|integer|min:1|max:5',
                'contenido' => 'sometimes|string',
                'usuarios_id_usuario' => 'sometimes|exists:users,id_usuario',
            ]);
            
            $comentario->update($validated);
            return response()->json($comentario);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Comentario no encontrado'], 404);
        }
    }

    /**
     * Mostrar todos los usuarios.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function usuarios()
    {
        // Importamos el modelo User
        $users = \App\Models\User::all();
        return response()->json($users);
    }
}
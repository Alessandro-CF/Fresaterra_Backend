<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comentario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ComentariosController extends Controller
{
    /**
     * Get all reviews for a specific product
     */
    public function getProductReviews($productId)
    {
        $producto = Producto::find($productId);
        
        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $comentarios = Comentario::where('productos_id_producto', $productId)
            ->with('usuario:id_usuario,nombre,apellidos')
            ->orderBy('fecha_creacion', 'desc')
            ->get();

        $averageRating = $comentarios->avg('calificacion');
        $totalReviews = $comentarios->count();

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => $comentarios,
                'average_rating' => round($averageRating ?? 0, 1),
                'total_reviews' => $totalReviews
            ]
        ]);
    }

    /**
     * Store a new review
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productos_id_producto' => 'required|exists:productos,id_producto',
            'calificacion' => 'required|integer|min:1|max:5',
            'contenido' => 'required|string|min:10|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        
        // Check if user already has a review for this product
        $existingReview = Comentario::where('usuarios_id_usuario', $user->id_usuario)
            ->where('productos_id_producto', $request->productos_id_producto)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'Ya has dejado una reseña para este producto. Puedes editarla si deseas.'
            ], 409);
        }

        $comentario = Comentario::create([
            'calificacion' => $request->calificacion,
            'contenido' => $request->contenido,
            'usuarios_id_usuario' => $user->id_usuario,
            'productos_id_producto' => $request->productos_id_producto,
            'fecha_creacion' => now()
        ]);

        $comentario->load('usuario:id_usuario,nombre,apellidos');

        return response()->json([
            'success' => true,
            'message' => 'Reseña agregada exitosamente',
            'data' => $comentario
        ], 201);
    }

    /**
     * Update an existing review
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'calificacion' => 'required|integer|min:1|max:5',
            'contenido' => 'required|string|min:10|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json([
                'success' => false,
                'message' => 'Reseña no encontrada'
            ], 404);
        }

        // Check if the review belongs to the authenticated user
        if ($comentario->usuarios_id_usuario !== $user->id_usuario) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar esta reseña'
            ], 403);
        }

        $comentario->update([
            'calificacion' => $request->calificacion,
            'contenido' => $request->contenido
        ]);

        $comentario->load('usuario:id_usuario,nombre,apellidos');

        return response()->json([
            'success' => true,
            'message' => 'Reseña actualizada exitosamente',
            'data' => $comentario
        ]);
    }

    /**
     * Get user's review for a specific product
     */
    public function getUserReview($productId)
    {
        $user = Auth::user();
        
        $comentario = Comentario::where('usuarios_id_usuario', $user->id_usuario)
            ->where('productos_id_producto', $productId)
            ->with('usuario:id_usuario,nombre,apellidos')
            ->first();

        if (!$comentario) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes reseña para este producto'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $comentario
        ]);
    }

    /**
     * Delete a review
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $comentario = Comentario::find($id);

        if (!$comentario) {
            return response()->json([
                'success' => false,
                'message' => 'Reseña no encontrada'
            ], 404);
        }

        if ($comentario->usuarios_id_usuario !== $user->id_usuario) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar esta reseña'
            ], 403);
        }

        $comentario->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reseña eliminada exitosamente'
        ]);
    }
}

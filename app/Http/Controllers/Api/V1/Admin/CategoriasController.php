<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CategoriasController extends Controller
{
    /**
     * Listar todas las categorías
     * GET /api/v1/admin/categories
     */
    public function index(Request $request)
    {
        try {
            $query = Categoria::withCount('productos');

            // Búsqueda por nombre
            if ($request->has('search')) {
                $query->where('nombre', 'like', '%' . $request->search . '%');
            }

            // Filtrar categorías con productos
            if ($request->has('with_products') && $request->with_products == 'true') {
                $query->whereHas('productos');
            }

            // Ordenamiento
            $orderBy = $request->get('order_by', 'fecha_creacion');
            $orderDirection = $request->get('order_direction', 'desc');
            $query->orderBy($orderBy, $orderDirection);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $categorias = $query->paginate($perPage);

            return response()->json([
                'message' => 'Categorías obtenidas exitosamente',
                'data' => $categorias->items(),
                'pagination' => [
                    'current_page' => $categorias->currentPage(),
                    'per_page' => $categorias->perPage(),
                    'total' => $categorias->total(),
                    'last_page' => $categorias->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener categorías: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Crear una nueva categoría
     * POST /api/v1/admin/categories
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:3|max:100|unique:categorias,nombre',
            'descripcion' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $categoria = Categoria::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'fecha_creacion' => now(),
            ]);

            return response()->json([
                'message' => 'Categoría creada exitosamente',
                'data' => $categoria
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear categoría: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Mostrar una categoría específica
     * GET /api/v1/admin/categories/{id}
     */
    public function show($id)
    {
        try {
            $categoria = Categoria::with(['productos' => function ($query) {
                $query->select('id_producto', 'nombre', 'precio', 'estado', 'categorias_id_categoria');
            }])->find($id);

            if (!$categoria) {
                return response()->json([
                    'message' => 'Categoría no encontrada'
                ], 404);
            }

            // Agregar estadísticas
            $categoria->total_productos = $categoria->productos()->count();
            $categoria->productos_activos = $categoria->productos()->where('estado', 1)->count();
            $categoria->productos_inactivos = $categoria->productos()->where('estado', 2)->count();

            return response()->json([
                'message' => 'Categoría obtenida exitosamente',
                'data' => $categoria
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener categoría: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar toda la información de una categoría
     * PUT /api/v1/admin/categories/{id}
     */
    public function update(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:3|max:100|unique:categorias,nombre,' . $id . ',id_categoria',
            'descripcion' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $categoria->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
            ]);

            return response()->json([
                'message' => 'Categoría actualizada exitosamente',
                'data' => $categoria->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al actualizar categoría: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualización parcial de una categoría
     * PATCH /api/v1/admin/categories/{id}
     */
    public function partialUpdate(Request $request, $id)
    {
        $categoria = Categoria::find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|min:3|max:100|unique:categorias,nombre,' . $id . ',id_categoria',
            'descripcion' => 'sometimes|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Solo actualizar los campos que se enviaron
            $updateData = [];
            $allowedFields = ['nombre', 'descripcion'];

            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            if (empty($updateData)) {
                return response()->json([
                    'message' => 'No se enviaron campos para actualizar'
                ], 400);
            }

            $categoria->update($updateData);

            return response()->json([
                'message' => 'Categoría actualizada exitosamente',
                'data' => $categoria->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al actualizar categoría parcialmente: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Eliminar una categoría (solo si no hay productos asociados)
     * DELETE /api/v1/admin/categories/{id}
     */
    public function destroy($id)
    {
        try {
            $categoria = Categoria::find($id);

            if (!$categoria) {
                return response()->json([
                    'message' => 'Categoría no encontrada'
                ], 404);
            }

            // Verificar si la categoría tiene productos asociados
            $productosCount = $categoria->productos()->count();
            if ($productosCount > 0) {
                return response()->json([
                    'message' => "No se puede eliminar la categoría porque tiene {$productosCount} producto(s) asociado(s)"
                ], 400);
            }

            $nombreCategoria = $categoria->nombre;
            $categoria->delete();

            return response()->json([
                'message' => "Categoría '{$nombreCategoria}' eliminada exitosamente"
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al eliminar categoría: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de categorías
     * GET /api/v1/admin/categories/statistics
     */
    public function statistics()
    {
        try {
            $totalCategorias = Categoria::count();
            $categoriasConProductos = Categoria::whereHas('productos')->count();
            $categoriasSinProductos = $totalCategorias - $categoriasConProductos;

            // Top 5 categorías con más productos
            $topCategorias = Categoria::withCount('productos')
                ->orderBy('productos_count', 'desc')
                ->limit(5)
                ->get(['id_categoria', 'nombre', 'productos_count']);

            return response()->json([
                'message' => 'Estadísticas de categorías obtenidas exitosamente',
                'data' => [
                    'total_categorias' => $totalCategorias,
                    'categorias_con_productos' => $categoriasConProductos,
                    'categorias_sin_productos' => $categoriasSinProductos,
                    'top_categorias' => $topCategorias
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas de categorías: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
}

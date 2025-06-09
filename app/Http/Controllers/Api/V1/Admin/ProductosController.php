<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProductosController extends Controller
{
    /**
     * Listar todos los productos (activos e inactivos)
     * GET /api/v1/admin/products
     */
    public function index(Request $request)
    {
        try {
            $query = Producto::with('categoria');

            // Filtrar por estado si se especifica
            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            // Filtrar por categoría si se especifica
            if ($request->has('categoria_id')) {
                $query->where('categorias_id_categoria', $request->categoria_id);
            }

            // Búsqueda por nombre
            if ($request->has('search')) {
                $query->where('nombre', 'like', '%' . $request->search . '%');
            }

            // Ordenamiento
            $orderBy = $request->get('order_by', 'fecha_creacion');
            $orderDirection = $request->get('order_direction', 'desc');
            $query->orderBy($orderBy, $orderDirection);

            // Paginación
            $perPage = $request->get('per_page', 15);
            $products = $query->paginate($perPage);

            return response()->json([
                'message' => 'Productos obtenidos exitosamente',
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener productos: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Crear un nuevo producto
     * POST /api/v1/admin/products
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:3|max:100',
            'descripcion' => 'required|string|min:10|max:510',
            'precio' => 'required|numeric|min:0',
            'url_imagen' => 'required|string|max:255',
            'estado' => 'required|in:1,2',
            'peso' => 'required|string|min:3|max:100',
            'categorias_id_categoria' => 'required|integer|exists:categorias,id_categoria',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $producto = Producto::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'precio' => $request->precio,
                'url_imagen' => $request->url_imagen,
                'estado' => $request->estado,
                'peso' => $request->peso,
                'categorias_id_categoria' => $request->categorias_id_categoria,
                'fecha_creacion' => now(),
            ]);

            return response()->json([
                'message' => 'Producto creado exitosamente',
                'data' => $producto->load('categoria')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al crear producto: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Mostrar un producto específico
     * GET /api/v1/admin/products/{id}
     */
    public function show($id)
    {
        try {
            $producto = Producto::with(['categoria', 'inventarios'])->find($id);

            if (!$producto) {
                return response()->json([
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            return response()->json([
                'message' => 'Producto obtenido exitosamente',
                'data' => $producto
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener producto: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualizar toda la información de un producto
     * PUT /api/v1/admin/products/{id}
     */
    public function update(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:3|max:100',
            'descripcion' => 'required|string|min:10|max:510',
            'precio' => 'required|numeric|min:0',
            'url_imagen' => 'required|string|max:255',
            'estado' => 'required|in:1,2',
            'peso' => 'required|string|min:3|max:100',
            'categorias_id_categoria' => 'required|integer|exists:categorias,id_categoria',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $producto->update([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'precio' => $request->precio,
                'url_imagen' => $request->url_imagen,
                'estado' => $request->estado,
                'peso' => $request->peso,
                'categorias_id_categoria' => $request->categorias_id_categoria,
            ]);

            return response()->json([
                'message' => 'Producto actualizado exitosamente',
                'data' => $producto->load('categoria')
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al actualizar producto: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Actualización parcial de un producto
     * PATCH /api/v1/admin/products/{id}
     */
    public function partialUpdate(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|min:3|max:100',
            'descripcion' => 'sometimes|string|min:10|max:510',
            'precio' => 'sometimes|numeric|min:0',
            'url_imagen' => 'sometimes|string|max:255',
            'estado' => 'sometimes|in:1,2',
            'peso' => 'sometimes|string|min:3|max:100',
            'categorias_id_categoria' => 'sometimes|integer|exists:categorias,id_categoria',
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
            $allowedFields = ['nombre', 'descripcion', 'precio', 'url_imagen', 'estado', 'peso', 'categorias_id_categoria'];

            foreach ($allowedFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }

            $producto->update($updateData);

            return response()->json([
                'message' => 'Producto actualizado exitosamente',
                'data' => $producto->load('categoria')
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al actualizar producto parcialmente: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Cambiar el estado de un producto (activar/desactivar)
     * PATCH /api/v1/admin/products/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json([
                'message' => 'Producto no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'estado' => 'required|in:1,2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $producto->update(['estado' => $request->estado]);

            $statusText = $request->estado == 1 ? 'activado' : 'desactivado';

            return response()->json([
                'message' => "Producto {$statusText} exitosamente",
                'data' => $producto->load('categoria')
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al cambiar estado del producto: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Listar productos con bajo inventario
     * GET /api/v1/admin/products/low-stock
     */
    public function lowStock(Request $request)
    {
        try {
            $stockMinimo = $request->get('stock_minimo', 10); // Por defecto 10 unidades

            $productos = Producto::with(['categoria', 'inventarios'])
                ->whereHas('inventarios', function ($query) use ($stockMinimo) {
                    $query->where('cantidad_stock', '<=', $stockMinimo);
                })
                ->where('estado', 1) // Solo productos activos
                ->get();

            if ($productos->isEmpty()) {
                return response()->json([
                    'message' => 'No se encontraron productos con bajo inventario',
                    'data' => []
                ], 200);
            }

            // Mapear productos con información de inventario
            $productosConStock = $productos->map(function ($producto) {
                $stockTotal = $producto->inventarios->sum('cantidad_stock');
                return [
                    'id_producto' => $producto->id_producto,
                    'nombre' => $producto->nombre,
                    'descripcion' => $producto->descripcion,
                    'precio' => $producto->precio,
                    'categoria' => $producto->categoria->nombre ?? 'Sin categoría',
                    'stock_total' => $stockTotal,
                    'estado' => $producto->estado,
                    'inventarios' => $producto->inventarios
                ];
            });

            return response()->json([
                'message' => 'Productos con bajo inventario obtenidos exitosamente',
                'data' => $productosConStock,
                'total' => $productosConStock->count()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al obtener productos con bajo stock: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Eliminar un producto (soft delete)
     * DELETE /api/v1/admin/products/{id}
     */
    public function destroy($id)
    {
        try {
            $producto = Producto::find($id);

            if (!$producto) {
                return response()->json([
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Verificar si el producto tiene pedidos asociados
            if ($producto->pedido_items()->exists()) {
                return response()->json([
                    'message' => 'No se puede eliminar el producto porque tiene pedidos asociados'
                ], 400);
            }

            $producto->delete();

            return response()->json([
                'message' => 'Producto eliminado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al eliminar producto: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
}

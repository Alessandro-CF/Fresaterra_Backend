<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Inventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        try {
            // Log temporal para debug
            Log::info('Frontend product creation attempt', [
                'all_data' => $request->all(),
                'files' => $request->allFiles(),
                'content_type' => $request->header('content-type'),
                'auth_header' => $request->header('authorization') ? 'present' : 'missing'
            ]);

            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|min:3|max:100',
                'descripcion' => 'required|string|min:10|max:510',
                'precio' => 'required|numeric|min:0',
                'stock' => 'sometimes|integer|min:0', // Campo opcional para compatibilidad
                'peso' => 'required|string|min:3|max:100',
                'estado' => 'sometimes|in:1,2', // Opcional, por defecto será 1 (activo)
                'categorias_id_categoria' => 'required|integer|exists:categorias,id_categoria',
                'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'url_imagen' => 'sometimes|string|max:255' // Para compatibilidad con el sistema anterior
            ]);

            if ($validator->fails()) {
                Log::error('Frontend validation failed', [
                    'errors' => $validator->errors()->toArray(),
                    'received_data' => $request->all()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            
            // Establecer valores por defecto
            $data['estado'] = $data['estado'] ?? '1';
            $data['fecha_creacion'] = now();

            // Manejar subida de imagen
            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');
                
                // Verificar que el archivo sea válido
                if (!$imagen->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El archivo de imagen no es válido'
                    ], 400);
                }

                // Generar nombre único para la imagen
                $extension = $imagen->getClientOriginalExtension();
                $nombreImagen = time() . '_' . uniqid() . '.' . $extension;
                
                // Crear directorio si no existe
                $directorio = 'productos';
                if (!Storage::disk('public')->exists($directorio)) {
                    Storage::disk('public')->makeDirectory($directorio);
                }
                
                // Guardar la imagen
                $rutaImagen = $imagen->storeAs($directorio, $nombreImagen, 'public');
                
                if (!$rutaImagen) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al guardar la imagen'
                    ], 500);
                }
                
                $data['url_imagen'] = $rutaImagen;
            } elseif (!isset($data['url_imagen'])) {
                // Si no se proporciona imagen ni URL, usar una imagen por defecto
                $data['url_imagen'] = 'productos/default.jpg';
            }

            // Remover el campo stock del array de datos ya que no existe en la tabla productos
            $stock = $data['stock'] ?? null;
            unset($data['stock']);

            $producto = Producto::create($data);
            
            // Si se proporcionó stock, crear entrada en inventario
            if ($stock !== null && $stock > 0) {
                Inventario::create([
                    'productos_id_producto' => $producto->id_producto,
                    'cantidad_stock' => $stock,
                    'fecha_actualizacion' => now()
                ]);
            }

            $producto->load('categoria');

            return response()->json([
                'success' => true,
                'message' => 'Producto creado exitosamente',
                'data' => $producto
            ], 201);

        } catch (\Exception $e) {
            // Si hay error y se subió una imagen, eliminarla
            if (isset($rutaImagen) && Storage::disk('public')->exists($rutaImagen)) {
                Storage::disk('public')->delete($rutaImagen);
            }
            
            Log::error('Error al crear producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al crear producto',
                'error' => $e->getMessage()
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
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        try {
            // Log temporal para debug
            Log::info('Frontend product update attempt', [
                'product_id' => $id,
                'all_data' => $request->all(),
                'files' => $request->allFiles()
            ]);

            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|min:3|max:100',
                'descripcion' => 'required|string|min:10|max:510',
                'precio' => 'required|numeric|min:0',
                'peso' => 'required|string|min:3|max:100',
                'estado' => 'sometimes|in:1,2',
                'categorias_id_categoria' => 'required|integer|exists:categorias,id_categoria',
                'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'url_imagen' => 'sometimes|string|max:255' // Para compatibilidad
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $imagenAnterior = $producto->url_imagen;

            // Manejar subida de nueva imagen
            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');
                
                // Verificar que el archivo sea válido
                if (!$imagen->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El archivo de imagen no es válido'
                    ], 400);
                }

                // Generar nombre único para la imagen
                $extension = $imagen->getClientOriginalExtension();
                $nombreImagen = time() . '_' . uniqid() . '.' . $extension;
                
                // Crear directorio si no existe
                $directorio = 'productos';
                if (!Storage::disk('public')->exists($directorio)) {
                    Storage::disk('public')->makeDirectory($directorio);
                }
                
                // Guardar la nueva imagen
                $rutaImagen = $imagen->storeAs($directorio, $nombreImagen, 'public');
                
                if (!$rutaImagen) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al guardar la imagen'
                    ], 500);
                }
                
                $data['url_imagen'] = $rutaImagen;
            }

            // Remover campos que no existen en la tabla
            unset($data['imagen']);

            $producto->update($data);

            // Si se actualizó la imagen y había una imagen anterior diferente a la default, eliminarla
            if (isset($rutaImagen) && 
                $imagenAnterior && 
                $imagenAnterior !== 'productos/default.jpg' && 
                $imagenAnterior !== $rutaImagen && // Evitar eliminar la misma imagen
                Storage::disk('public')->exists($imagenAnterior)) {
                
                try {
                    Storage::disk('public')->delete($imagenAnterior);
                    Log::info("Imagen anterior eliminada: {$imagenAnterior}");
                } catch (\Exception $e) {
                    Log::warning("No se pudo eliminar imagen anterior: {$imagenAnterior}. Error: " . $e->getMessage());
                    // No fallar la operación por esto
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => $producto->load('categoria')
            ], 200);

        } catch (\Exception $e) {
            // Si hay error y se subió una imagen nueva, eliminarla
            if (isset($rutaImagen) && Storage::disk('public')->exists($rutaImagen)) {
                Storage::disk('public')->delete($rutaImagen);
            }
            
            Log::error('Error al actualizar producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar producto',
                'error' => $e->getMessage()
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
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'sometimes|string|min:3|max:100',
                'descripcion' => 'sometimes|string|min:10|max:510',
                'precio' => 'sometimes|numeric|min:0',
                'peso' => 'sometimes|string|min:3|max:100',
                'estado' => 'sometimes|in:1,2',
                'categorias_id_categoria' => 'sometimes|integer|exists:categorias,id_categoria',
                'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'url_imagen' => 'sometimes|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();
            $imagenAnterior = $producto->url_imagen;

            // Manejar subida de nueva imagen
            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');
                
                // Verificar que el archivo sea válido
                if (!$imagen->isValid()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'El archivo de imagen no es válido'
                    ], 400);
                }

                // Generar nombre único para la imagen
                $extension = $imagen->getClientOriginalExtension();
                $nombreImagen = time() . '_' . uniqid() . '.' . $extension;
                
                // Crear directorio si no existe
                $directorio = 'productos';
                if (!Storage::disk('public')->exists($directorio)) {
                    Storage::disk('public')->makeDirectory($directorio);
                }
                
                // Guardar la nueva imagen
                $rutaImagen = $imagen->storeAs($directorio, $nombreImagen, 'public');
                
                if (!$rutaImagen) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al guardar la imagen'
                    ], 500);
                }
                
                $data['url_imagen'] = $rutaImagen;
            }

            // Solo actualizar los campos que se enviaron
            $updateData = [];
            $allowedFields = ['nombre', 'descripcion', 'precio', 'url_imagen', 'estado', 'peso', 'categorias_id_categoria'];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (!empty($updateData)) {
                $producto->update($updateData);
            }

            // Si se actualizó la imagen y había una imagen anterior diferente a la default, eliminarla
            if (isset($rutaImagen) && 
                $imagenAnterior && 
                $imagenAnterior !== 'productos/default.jpg' && 
                $imagenAnterior !== $rutaImagen && // Evitar eliminar la misma imagen
                Storage::disk('public')->exists($imagenAnterior)) {
                
                try {
                    Storage::disk('public')->delete($imagenAnterior);
                    Log::info("Imagen anterior eliminada: {$imagenAnterior}");
                } catch (\Exception $e) {
                    Log::warning("No se pudo eliminar imagen anterior: {$imagenAnterior}. Error: " . $e->getMessage());
                    // No fallar la operación por esto
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => $producto->load('categoria')
            ], 200);

        } catch (\Exception $e) {
            // Si hay error y se subió una imagen nueva, eliminarla
            if (isset($rutaImagen) && Storage::disk('public')->exists($rutaImagen)) {
                Storage::disk('public')->delete($rutaImagen);
            }
            
            Log::error('Error al actualizar producto parcialmente: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar producto',
                'error' => $e->getMessage()
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
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Verificar si el producto tiene pedidos asociados
            if ($producto->pedido_items()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el producto porque tiene pedidos asociados'
                ], 400);
            }

            // Guardar la ruta de la imagen antes de eliminar el producto
            $imagenPath = $producto->url_imagen;

            $producto->delete();

            // Eliminar la imagen del storage si existe y no es la imagen por defecto
            if ($imagenPath && 
                $imagenPath !== 'productos/default.jpg' && 
                Storage::disk('public')->exists($imagenPath)) {
                Storage::disk('public')->delete($imagenPath);
            }

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado exitosamente'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al eliminar producto: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

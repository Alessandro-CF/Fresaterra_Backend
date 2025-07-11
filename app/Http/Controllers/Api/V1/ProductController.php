<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\Categoria;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Listar todos los productos con filtros opcionales
     */
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->activos()
            ->withAvg('comentarios', 'calificacion')
            ->withCount('comentarios');

        // Filtro por categoría
        if ($request->has('categoria') && $request->categoria) {
            $query->categoria($request->categoria);
        }

        // Filtro por búsqueda (soporte para 'search' y 'busqueda')
        $searchTerm = $request->get('search') ?? $request->get('busqueda');
        if ($searchTerm) {
            $query->buscar($searchTerm);
        }

        // Filtro por rango de precios
        if ($request->has('precio_min') && $request->precio_min) {
            $query->where('precio', '>=', $request->precio_min);
        }
        if ($request->has('precio_max') && $request->precio_max) {
            $query->where('precio', '<=', $request->precio_max);
        }

        // Filtro por rating mínimo
        if ($request->has('rating_min') && $request->rating_min) {
            $query->having('comentarios_avg_calificacion', '>=', $request->rating_min);
        }

        // Filtro por productos con stock
        if ($request->has('solo_disponibles') && $request->solo_disponibles == 'true') {
            $query->where('estado', 'activo');
        }

        // Ordenamiento
        $sortBy = $request->get('ordenar', 'fecha_creacion');
        $sortOrder = $request->get('direccion', 'desc');

        switch ($sortBy) {
            case 'precio':
                $query->orderBy('precio', $sortOrder);
                break;
            case 'nombre':
                $query->orderBy('nombre', $sortOrder);
                break;
            case 'rating':
                $query->orderByRaw('COALESCE(comentarios_avg_calificacion, 0) ' . $sortOrder);
                break;
            case 'popularidad':
                $query->orderBy('comentarios_count', 'desc')
                      ->orderByRaw('COALESCE(comentarios_avg_calificacion, 0) DESC');
                break;
            case 'relevancia':
                // Primero productos destacados, luego por rating y popularidad
                $query->orderByRaw('(CASE WHEN comentarios_avg_calificacion >= 4.0 OR comentarios_count = 0 THEN 1 ELSE 0 END) DESC')
                      ->orderByRaw('COALESCE(comentarios_avg_calificacion, 0) DESC')
                      ->orderBy('comentarios_count', 'desc');
                break;
            default:
                $query->orderBy('fecha_creacion', $sortOrder);
                break;
        }

        // Paginación
        $perPage = min($request->get('por_pagina', 12), 50); // Máximo 50 productos por página
        $productos = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Productos recuperados exitosamente',
            'data' => $productos,
            'filtros_aplicados' => [
                'categoria' => $request->categoria,
                'busqueda' => $request->busqueda,
                'precio_min' => $request->precio_min,
                'precio_max' => $request->precio_max,
                'rating_min' => $request->rating_min,
                'ordenar' => $sortBy,
                'direccion' => $sortOrder
            ]
        ]);
    }

    /**
     * Mostrar un producto específico
     */
    public function show(int $id): JsonResponse
    {
        $producto = Producto::with(['categoria', 'comentarios'])
            ->withAvg('comentarios', 'calificacion')
            ->withCount('comentarios')
            ->find($id);

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Producto recuperado exitosamente',
            'data' => $producto
        ]);
    }

    /**
     * Listar productos destacados
     */
    public function featured(): JsonResponse
    {
        $productos = Producto::destacados()->get();

        if ($productos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron productos destacados'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Productos destacados recuperados exitosamente',
            'data' => $productos
        ]);
    }

    /**
     * Listar todas las categorías
     */
    public function categories(): JsonResponse
    {
        $categorias = Categoria::all();

        if ($categorias->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron categorías'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Categorías recuperadas exitosamente',
            'data' => $categorias
        ]);
    }

    /**
     * Obtener estadísticas de productos para filtros dinámicos
     */
    public function stats(): JsonResponse
    {
        $stats = Producto::activos()
            ->withAvg('comentarios', 'calificacion')
            ->withCount('comentarios')
            ->get();

        if ($stats->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay productos disponibles para generar estadísticas'
            ], 404);
        }

        // Calcular estadísticas de precios
        $precios = $stats->pluck('precio');
        $precioMin = $precios->min();
        $precioMax = $precios->max();

        // Calcular estadísticas de ratings
        $ratings = $stats->filter(function ($producto) {
            return $producto->comentarios_avg_calificacion > 0;
        })->pluck('comentarios_avg_calificacion');

        $ratingMin = $ratings->isNotEmpty() ? $ratings->min() : 0;
        $ratingMax = $ratings->isNotEmpty() ? $ratings->max() : 5;

        // Contar productos por categoría
        $productosPorCategoria = $stats->groupBy('categorias_id_categoria')
            ->map(function ($productos, $categoriaId) {
                $categoria = $productos->first()->categoria;
                return [
                    'categoria_id' => $categoriaId,
                    'categoria_nombre' => $categoria ? $categoria->nombre : 'Sin categoría',
                    'total_productos' => $productos->count()
                ];
            })->values();

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas recuperadas exitosamente',
            'data' => [
                'total_productos' => $stats->count(),
                'precio' => [
                    'min' => round($precioMin, 2),
                    'max' => round($precioMax, 2),
                    'promedio' => round($precios->avg(), 2)
                ],
                'rating' => [
                    'min' => round($ratingMin, 1),
                    'max' => round($ratingMax, 1),
                    'promedio' => round($ratings->avg() ?? 0, 1)
                ],
                'productos_por_categoria' => $productosPorCategoria,
                'productos_con_reviews' => $stats->where('comentarios_count', '>', 0)->count(),
                'productos_destacados' => $stats->filter(function ($producto) {
                    return $producto->comentarios_avg_calificacion >= 4.0 || $producto->comentarios_count == 0;
                })->count()
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:100',
            'descripcion' => 'required|string',
            'precio' => 'required|numeric|min:0',
            'peso' => 'required|string|min:0',
            'categorias_id_categoria' => 'required|exists:categorias,id_categoria',
            'url_imagen' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Procesar y guardar la imagen
            $nombreImagen = null;
            if ($request->hasFile('url_imagen')) {
                $imagen = $request->file('url_imagen');
                
                // OPCIÓN 1: Usar nombre del producto + timestamp
                $nombreProducto = Str::slug($request->nombre); // Convierte "Fresas Premium" a "fresas-premium"
                $extension = $imagen->getClientOriginalExtension();
                $timestamp = now()->format('YmdHis');
                $nombreImagen = $nombreProducto . '_' . $timestamp . '.' . $extension;
                
                // OPCIÓN 2: Mantener nombre original con timestamp (descomenta si prefieres esta)
                // $originalName = pathinfo($imagen->getClientOriginalName(), PATHINFO_FILENAME);
                // $nombreImagen = Str::slug($originalName) . '_' . $timestamp . '.' . $extension;
                
                // OPCIÓN 3: Solo timestamp (más corto pero menos descriptivo)
                // $nombreImagen = $timestamp . '.' . $extension;
                
                // OPCIÓN 4: UUID (actual - más seguro pero menos legible)
                // $nombreImagen = Str::uuid() . '.' . $imagen->getClientOriginalExtension();

                // Guardar la imagen en storage/app/public/productos
                $storedPath = $imagen->storeAs('productos', $nombreImagen, 'public');
                
                // Verificar que la imagen se guardó correctamente
                if (!$storedPath) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al guardar la imagen'
                    ], 500);
                }

                // Verificar que el archivo existe físicamente
                $fullPath = storage_path('app/public/productos/' . $nombreImagen);
                if (!file_exists($fullPath)) { 
                    return response()->json([
                        'success' => false,
                        'message' => 'La imagen no se guardó correctamente'
                    ], 500);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'La imagen es obligatoria',
                        'errors' => ['url_imagen' => ['El campo url_imagen es obligatorio']]
                    ], 422);
                }
            }

            // Crear el producto
            $producto = Producto::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'precio' => $request->precio,
                'peso' => $request->peso,
                'estado' => 'activo',
                'categorias_id_categoria' => $request->categorias_id_categoria,
                'url_imagen' => 'productos/' . $nombreImagen,
                'fecha_creacion' => now()
            ]);

            // Cargar la relación de categoría y forzar la generación de la URL de imagen
            $producto->load('categoria');

            return response()->json([
                'success' => true,
                'message' => 'Producto creado exitosamente',
                'data' => $producto
            ], 201);
        } catch (\Exception $e) {
            // Si algo sale mal, eliminar la imagen si se subió
            if (isset($nombreImagen)) {
                Storage::delete('public/productos/' . $nombreImagen);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|required|string|max:100',
            'descripcion' => 'sometimes|required|string',
            'precio' => 'sometimes|required|numeric|min:0',
            'peso' => 'sometimes|required|string',
            'categorias_id_categoria' => 'sometimes|required|exists:categorias,id_categoria',
            'estado' => 'sometimes|required|string|in:activo,inactivo',
            'url_imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        try {
            $producto = Producto::find($id);
            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            $originalUrlImagen = $producto->url_imagen;
            $newImageNameForCleanup = null;

            if ($request->hasFile('url_imagen')) {
                $imagen = $request->file('url_imagen');
                $nombreImagen = Str::uuid() . '.' . $imagen->getClientOriginalExtension();
                $imagen->storeAs('productos', $nombreImagen, 'public');
                $newImageNameForCleanup = $nombreImagen;
                $producto->url_imagen = 'productos/' . $nombreImagen;

                if ($originalUrlImagen && $originalUrlImagen !== $producto->url_imagen && $originalUrlImagen !== ('productos/' . $nombreImagen)) {
                    Storage::delete('public/' . $originalUrlImagen);
                }
            }

            $attributesToFill = $validatedData;
            if (array_key_exists('url_imagen', $attributesToFill)) {
                unset($attributesToFill['url_imagen']);
            }

            if (!empty($attributesToFill)) {
                $producto->fill($attributesToFill);
            }

            $cambiosDetectadosEnModelo = $producto->isDirty();

            if ($cambiosDetectadosEnModelo) {
                $producto->save();
            }

            $producto->load('categoria');
            $producto->refresh();

            $message = 'Producto procesado.';
            $finalChangesPersisted = $producto->wasChanged() || ($request->hasFile('url_imagen') && $producto->url_imagen !== $originalUrlImagen);

            if ($finalChangesPersisted) {
                $message = 'Producto actualizado exitosamente.';
            } else {
                $message = 'Producto procesado sin cambios detectados.';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $producto,
                'cambios_realizados' => $finalChangesPersisted,
            ]);
        } catch (\Exception $e) {
            if ($newImageNameForCleanup && $request->hasFile('url_imagen')) {
                if ($producto->url_imagen === ('productos/' . $newImageNameForCleanup) && $originalUrlImagen !== ('productos/' . $newImageNameForCleanup)) {
                    Storage::delete('public/productos/' . $newImageNameForCleanup);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el producto.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $producto = Producto::find($id);

            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Eliminar la imagen asociada si existe
            if ($producto->url_imagen) {
                Storage::delete('public/' . $producto->url_imagen);
            }

            // Verificar si hay relaciones que impidan eliminación
            if ($producto->carrito_items()->count() > 0 || $producto->pedido_items()->count() > 0) {
                // En lugar de eliminar, marcar como inactivo
                $producto->estado = 'inactivo';
                $producto->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Producto marcado como inactivo porque está siendo utilizado',
                    'data' => $producto
                ]);
            }

            // Si no hay relaciones, eliminar completamente
            $producto->delete();

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Búsqueda específica con autocompletado
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100',
            'limit' => 'integer|min:1|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros de búsqueda inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = $request->get('q');
        $limit = $request->get('limit', 10);

        // Búsqueda con mayor relevancia
        $productos = Producto::query()
            ->activos()
            ->withAvg('comentarios', 'calificacion')
            ->withCount('comentarios')
            ->where(function($q) use ($query) {
                $q->where('nombre', 'LIKE', "%{$query}%")
                  ->orWhere('descripcion', 'LIKE', "%{$query}%")
                  ->orWhereHas('categoria', function($subQuery) use ($query) {
                      $subQuery->where('nombre', 'LIKE', "%{$query}%");
                  });
            })
            // Ordenar por relevancia: primero coincidencias exactas en nombre
            ->orderByRaw("CASE 
                WHEN nombre LIKE ? THEN 1 
                WHEN nombre LIKE ? THEN 2 
                WHEN descripcion LIKE ? THEN 3 
                ELSE 4 
            END", [
                $query, 
                "%{$query}%", 
                "%{$query}%"
            ])
            ->orderByRaw('COALESCE(comentarios_avg_calificacion, 0) DESC')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Búsqueda completada',
            'data' => $productos->map(function($producto) {
                return [
                    'id_producto' => $producto->id_producto,
                    'nombre' => $producto->nombre,
                    'descripcion' => Str::limit($producto->descripcion, 100),
                    'precio' => $producto->precio,
                    'url_imagen' => $producto->imagen_url, // Usar el accessor que ya maneja la URL completa
                    'url_imagen_completa' => $producto->imagen_url, // También incluir como url_imagen_completa
                    'rating' => round($producto->comentarios_avg_calificacion ?? 0, 1),
                    'categoria' => $producto->categoria?->nombre,
                    'estado' => $producto->estado
                ];
            }),
            'query' => $query,
            'total' => $productos->count()
        ]);
    }

}

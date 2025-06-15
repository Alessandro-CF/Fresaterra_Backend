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
        $productos = Producto::query()
            ->activos()
            ->when($request->has('categoria'), function ($query) use ($request) {
                return $query->categoria($request->categoria);
            })
            ->when($request->has('busqueda'), function ($query) use ($request) {
                return $query->buscar($request->busqueda);
            })
            ->orderBy('fecha_creacion', 'desc')
            ->paginate(12);

        if ($productos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron productos'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Productos recuperados exitosamente',
            'data' => $productos
        ]);
    }

    /**
     * Mostrar un producto específico
     */
    public function show(int $id): JsonResponse
    {
        $producto = Producto::find($id);

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
            // Procesar y guardar la imagen
            $nombreImagen = null;
            if ($request->hasFile('url_imagen')) {
                $imagen = $request->file('url_imagen');
                $nombreImagen = Str::uuid() . '.' . $imagen->getClientOriginalExtension();

                // Guardar la imagen en storage/app/public/productos
                $imagen->storeAs('productos', $nombreImagen, 'public');
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'La imagen es obligatoria',
                    'errors' => ['url_imagen' => ['El campo url_imagen es obligatorio']]
                ], 422);
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

}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
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
        $productos = Product::query()
            ->activos()
            ->when($request->has('categoria'), function($query) use ($request) {
                return $query->categoria($request->categoria);
            })
            ->when($request->has('busqueda'), function($query) use ($request) {
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
        $producto = Product::find($id);

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
        $productos = Product::destacados()->get();

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
        $categorias = Category::all();

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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
            'peso' => 'required|numeric|min:0',
            'categorias_id_categoria' => 'required|exists:categorias,id_categoria',
            'imagen' => 'required|image|mimes:jpeg,png,jpg|max:2048'
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
            if ($request->hasFile('imagen')) {
                $imagen = $request->file('imagen');
                $nombreImagen = Str::uuid() . '.' . $imagen->getClientOriginalExtension();
                
                // Guardar la imagen en storage/app/public/productos
                $imagen->storeAs('public/productos', $nombreImagen);
            }

            // Crear el producto
            $producto = Product::create([
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'precio' => $request->precio,
                'peso' => $request->peso,
                'estado' => 'disponible',
                'categorias_id_categoria' => $request->categorias_id_categoria,
                'url_imagen' => $nombreImagen ? 'productos/' . $nombreImagen : null,
                'fecha_creacion' => now()
            ]);

            // Generar URL completa de la imagen
            $producto->url_imagen_completa = $producto->url_imagen ? url('storage/' . $producto->url_imagen) : null;

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

            \Log::error('Error al crear producto: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Producto $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'string|max:100',
            'descripcion' => 'string',
            'precio' => 'numeric|min:0',
            'peso' => 'numeric|min:0',
            'categorias_id_categoria' => 'exists:categorias,id_categoria',
            'imagen' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $producto = Product::find($id);
            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado'
                ], 404);
            }

            // Procesar y guardar la nueva imagen si se proporciona
            if ($request->hasFile('imagen')) {
                // Eliminar la imagen anterior si existe
                if ($producto->url_imagen) {
                    Storage::delete('public/' . $producto->url_imagen);
                }

                $imagen = $request->file('imagen');
                $nombreImagen = Str::uuid() . '.' . $imagen->getClientOriginalExtension();
                $imagen->storeAs('public/productos', $nombreImagen);
                $producto->url_imagen = 'productos/' . $nombreImagen;
            }

            // Actualizar otros campos si se proporcionan
            if ($request->has('nombre')) $producto->nombre = $request->nombre;
            if ($request->has('descripcion')) $producto->descripcion = $request->descripcion;
            if ($request->has('precio')) $producto->precio = $request->precio;
            if ($request->has('peso')) $producto->peso = $request->peso;
            if ($request->has('categorias_id_categoria')) $producto->categorias_id_categoria = $request->categorias_id_categoria;

            $producto->save();

            // Generar URL completa de la imagen
            $producto->url_imagen_completa = $producto->url_imagen ? Storage::url($producto->url_imagen) : null;

            return response()->json([
                'success' => true,
                'message' => 'Producto actualizado exitosamente',
                'data' => $producto
            ]);

        } catch (\Exception $e) {
            // Si algo sale mal y se subió una nueva imagen, eliminarla
            if (isset($nombreImagen)) {
                Storage::delete('public/productos/' . $nombreImagen);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el producto',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $product = Producto::find($id);
        if(!$product) {
                return response()->json([
                'message' => 'Producto no encontrado'],
                404
            );
        }
        $product->delete();
        return response()->json([
            'message' => 'Producto eliminado correctamente'
        ], 200
        );
    }
}

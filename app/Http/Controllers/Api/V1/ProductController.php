<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = Product::all();

        if($products->isEmpty()) {
            return response()->json([
                'message' => 'Productos no encontrados'],
                404
            );
        }

        return response()->json($products, 200);
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|min:10|max:100',
            'descripcion' => 'required|string|min:32|max:510',
            'precio' => 'required|numeric',
            'url_imagen' => 'required|string',
            'estado' => 'required|in:1,2',
            'peso'  => 'required|string|min:10|max:100',
            'fecha_creacion' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()],
               422
            );
        }

        Product::create([
            'nombre' => $request->get('nombre'),
            'descripcion' => $request->get('descripcion'),
            'precio' => $request->get('precio'),
            'url_imagen' => $request->get('url_imagen'),
            'estado' => $request->get('estado'),
            'peso' => $request->get('peso'),
            'fecha_creacion' => $request->get('fecha_creacion'),
        ]);
        return response()->json([
            'message' => 'Producto creado correcatmente'],
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $product = Product::find($id);
        if(!$product) {
                return response()->json([
                'message' => 'Product not found'
            ], 404
            );
        }
        return response()->json($product, 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);
        if(!$product) {
                return response()->json([
                'message' => 'Producto no encontrado'],
                404
            );
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|min:10|max:100',
            'descripcion' => 'sometimes|string|min:32|max:510',
            'precio' => 'sometimes|numeric',
            'url_imagen' => 'sometimes|string',
            'estado' => 'sometimes|in:1,2',
            'peso'  => 'sometimes|string|min:10|max:100',
            'fecha_creacion' => 'sometimes|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()],
               422
            );
        }

        if($request->has('nombre')) {
            $product->nombre = $request->nombre;
        }
        if($request->has('descripcion')) {
            $product->descripcion = $request->descripcion;
        }
        if($request->has('precio')) {
            $product->precio = $request->precio;
        }
        if($request->has('url_imagen')) {
            $product->url_imagen = $request->url_imagen;
        }
        if($request->has('estado')) {
            $product->estado = $request->estado;
        }
        if($request->has('peso')) {
            $product->peso = $request->peso;
        }
        if($request->has('fecha_creacion')) {
            $product->fecha_creacion = $request->fecha_creacion;
        }
        $product->update();
        return response()->json([
            'message' => 'Producto actualizado correctamente'],
            200
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $product = Product::find($id);
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

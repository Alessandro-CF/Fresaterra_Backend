<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto; // 

class ProductoController extends Controller
{
    // GET /api/producto
    public function index()
    {
        return Producto::all();
    }

    // POST /api/producto
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'url_imagen' => 'nullable|url|max:255',
            'estado' => 'required|in:activo,inactivo',
            'peso' => 'nullable|numeric|min:0'
        ]);

        $producto = Producto::create($validated);
        return response()->json($producto, 201);
    }

    // GET /api/producto/{id}
    public function show($id)
    {
        return Producto::findOrFail($id);
    }

    // PUT/PATCH /api/producto/{id}
    public function update(Request $request, $id)
    {
        $producto = Producto::findOrFail($id);
        
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'precio' => 'sometimes|numeric|min:0',
            'url_imagen' => 'nullable|url|max:255',
            'estado' => 'sometimes|in:activo,inactivo',
            'peso' => 'nullable|numeric|min:0'
        ]);

        $producto->update($validated);
        return response()->json($producto);
    }

    // DELETE /api/producto/{id}
    public function destroy($id)
    {
        $producto = Producto::findOrFail($id);
        $producto->delete();
        return response()->json(null, 204);
    }
}
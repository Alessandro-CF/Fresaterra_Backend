<?php

namespace App\Http\Controllers;

use App\Models\Carrito;
use App\Models\CarritoItem;
use Illuminate\Http\Request;

class CarritoController extends Controller
{
     // Listar carrito completo (GET /cart/api/v1/)
    public function index()
    {
        // Aquí puedes usar un carrito de un usuario fijo, o general
        $carrito = Carrito::with('items.producto')->first(); 
        return response()->json($carrito);
    }

    // Obtener un item específico (GET /cart/api/v1/{id})
    public function show($id)
    {
        $item = CarritoItem::with('producto')->findOrFail($id);
        return response()->json($item);
    }

    // Agregar producto al carrito (POST /cart/api/v1/)
    public function store(Request $request)
    {
        $data = $request->validate([
            'carritos_id_carrito' => 'required|exists:carritos,id_carrito',
            'productos_id_producto' => 'required|exists:productos,id_producto',
            'cantidad' => 'required|integer|min:1',
        ]);

        // Verificar si el producto ya existe en el carrito, y actualizar cantidad
        $item = CarritoItem::where('carritos_id_carrito', $data['carritos_id_carrito'])
            ->where('productos_id_producto', $data['productos_id_producto'])
            ->first();

        if ($item) {
            $item->cantidad += $data['cantidad'];
            $item->save();
        } else {
            $item = CarritoItem::create($data);
        }

        return response()->json($item, 201);
    }

    // Actualizar cantidad de un item (PUT /cart/api/v1/{id})
    public function update(Request $request, $id)
    {
        $item = CarritoItem::findOrFail($id);

        $data = $request->validate([
            'cantidad' => 'required|integer|min:1',
        ]);

        $item->cantidad = $data['cantidad'];
        $item->save();

        return response()->json($item);
    }
}
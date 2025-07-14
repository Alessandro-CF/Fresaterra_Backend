<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pedido;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PedidoController extends Controller
{
    // Actualizar estado para admin usando constantes de enum
    public function adminUpdateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'estado' => 'required|string|in:' . implode(',', Pedido::getEstados())
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()
            ], 400);
        }
        $pedido = \App\Models\Pedido::find($id);
        if (!$pedido) {
            return response()->json([
                'error' => 'Pedido no encontrado'
            ], 404);
        }
        $pedido->estado = $request->estado;
        $pedido->save();
        return response()->json([
            'mensaje' => 'Estado actualizado',
            'id_pedido' => $pedido->id_pedido,
            'estado' => $pedido->estado
        ], 200);
    }
    // Listar todos los pedidos con sus relaciones principales
    public function index()
    {
        $pedidos = \App\Models\Pedido::with(['usuario', 'envios', 'pagos', 'pedido_items'])->get();
        return response()->json([
            'message' => 'Pedidos obtenidos exitosamente',
            'orders' => $pedidos,
            'total' => $pedidos->count(),
        ]);
    }

    // Mostrar un pedido específico por ID
    public function show($id)
    {
        $pedido = \App\Models\Pedido::with(['usuario', 'envios', 'pagos', 'pedido_items'])->findOrFail($id);
        return response()->json($pedido);
    }
}

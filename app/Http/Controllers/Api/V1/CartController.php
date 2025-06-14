<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Obtener el carrito actual del usuario
     */
    public function index(): JsonResponse
    {
        try {
            $cart = Cart::where('usuarios_id_usuario', Auth::id())
                       ->where('estado', 'activo')
                       ->first();

            if (!$cart) {
                $cart = Cart::create([
                    'usuarios_id_usuario' => Auth::id(),
                    'estado' => 'activo'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Carrito recuperado exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al recuperar el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar un producto al carrito
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'producto_id' => 'required|exists:productos,id_producto',
            'cantidad' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Obtener o crear el carrito activo del usuario
            $cart = Cart::firstOrCreate(
                [
                    'usuarios_id_usuario' => Auth::id(),
                    'estado' => 'activo'
                ]
            );

            // Verificar si el producto existe y está disponible
            $producto = Product::find($request->producto_id);
            if (!$producto || $producto->estado !== 'disponible') {
                return response()->json([
                    'success' => false,
                    'message' => 'El producto no está disponible'
                ], 400);
            }

            // Buscar si el producto ya está en el carrito
            $cartItem = CartItem::where('carritos_id_carrito', $cart->id_carrito)
                              ->where('productos_id_producto', $request->producto_id)
                              ->first();

            if ($cartItem) {
                // Actualizar cantidad si ya existe
                $cartItem->cantidad += $request->cantidad;
                $cartItem->save();
            } else {
                // Crear nuevo item si no existe
                $cartItem = CartItem::create([
                    'carritos_id_carrito' => $cart->id_carrito,
                    'productos_id_producto' => $request->producto_id,
                    'cantidad' => $request->cantidad
                ]);
            }

            // Recargar el carrito con sus items
            $cart->load('items.producto');

            return response()->json([
                'success' => true,
                'message' => 'Producto agregado al carrito exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al agregar el producto al carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar la cantidad de un producto en el carrito
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cantidad' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $cartItem = CartItem::findOrFail($id);
            
            // Verificar que el item pertenece al carrito del usuario
            $cart = Cart::where('id_carrito', $cartItem->carritos_id_carrito)
                       ->where('usuarios_id_usuario', Auth::id())
                       ->where('estado', 'activo')
                       ->firstOrFail();

            if ($request->cantidad === 0) {
                // Eliminar el item si la cantidad es 0
                $cartItem->delete();
            } else {
                // Actualizar la cantidad
                $cartItem->cantidad = $request->cantidad;
                $cartItem->save();
            }

            // Recargar el carrito con sus items
            $cart->load('items.producto');

            return response()->json([
                'success' => true,
                'message' => 'Carrito actualizado exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un item del carrito
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $cartItem = CartItem::findOrFail($id);
            
            // Verificar que el item pertenece al carrito del usuario
            $cart = Cart::where('id_carrito', $cartItem->carritos_id_carrito)
                       ->where('usuarios_id_usuario', Auth::id())
                       ->where('estado', 'activo')
                       ->firstOrFail();

            $cartItem->delete();

            // Recargar el carrito con sus items
            $cart->load('items.producto');

            return response()->json([
                'success' => true,
                'message' => 'Item eliminado del carrito exitosamente',
                'data' => [
                    'cart' => $cart,
                    'total' => $cart->getTotal()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el item del carrito',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 
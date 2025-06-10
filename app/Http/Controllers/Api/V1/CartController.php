<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Obtener el carrito activo de un usuario
     */
    public function show(int $userId): JsonResponse
    {
        $user = User::find($userId);
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $cart = $user->carritoActivo();
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene un carrito activo'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carrito recuperado exitosamente',
            'data' => [
                'carrito' => $cart,
                'total' => $cart->getTotal()
            ]
        ]);
    }

    /**
     * Crear un nuevo carrito para un usuario
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'usuarios_id_usuario' => 'required|exists:usuarios,id_usuario'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::find($request->usuarios_id_usuario);

        if ($user->carritoActivo()) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario ya tiene un carrito activo'
            ], 400);
        }

        $cart = Cart::create([
            'estado' => 'activo',
            'usuarios_id_usuario' => $request->usuarios_id_usuario,
            'fecha_creacion' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Carrito creado exitosamente',
            'data' => $cart
        ], 201);
    }

    /**
     * Agregar un producto al carrito
     */
    public function addItem(Request $request, int $cartId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'productos_id_producto' => 'required|exists:productos,id_producto',
            'cantidad' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $cart = Cart::find($cartId);
        if (!$cart || $cart->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'Carrito no encontrado o no activo'
            ], 404);
        }

        $cartItem = CartItem::where('carritos_id_carrito', $cartId)
            ->where('productos_id_producto', $request->productos_id_producto)
            ->first();

        if ($cartItem) {
            $cartItem->cantidad += $request->cantidad;
            $cartItem->save();
        } else {
            $cartItem = CartItem::create([
                'cantidad' => $request->cantidad,
                'carritos_id_carrito' => $cartId,
                'productos_id_producto' => $request->productos_id_producto
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Producto agregado al carrito exitosamente',
            'data' => $cartItem
        ]);
    }

    /**
     * Actualizar la cantidad de un producto en el carrito
     */
    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cantidad' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $cartItem = CartItem::find($itemId);
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item no encontrado'
            ], 404);
        }

        if ($cartItem->carrito->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'El carrito no está activo'
            ], 400);
        }

        $cartItem->cantidad = $request->cantidad;
        $cartItem->save();

        return response()->json([
            'success' => true,
            'message' => 'Cantidad actualizada exitosamente',
            'data' => $cartItem
        ]);
    }

    /**
     * Eliminar un producto del carrito
     */
    public function deleteItem(int $itemId): JsonResponse
    {
        $cartItem = CartItem::find($itemId);
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item no encontrado'
            ], 404);
        }

        if ($cartItem->carrito->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'El carrito no está activo'
            ], 400);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado del carrito exitosamente'
        ]);
    }

    /**
     * Vaciar el carrito
     */
    public function empty(int $cartId): JsonResponse
    {
        $cart = Cart::find($cartId);
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrito no encontrado'
            ], 404);
        }

        if ($cart->estado !== 'activo') {
            return response()->json([
                'success' => false,
                'message' => 'El carrito no está activo'
            ], 400);
        }

        $cart->vaciar();

        return response()->json([
            'success' => true,
            'message' => 'Carrito vaciado exitosamente'
        ]);
    }
} 
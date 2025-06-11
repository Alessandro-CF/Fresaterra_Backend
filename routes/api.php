<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MercadoPagoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\DireccionesController;
use App\Http\Controllers\Api\V1\PagosController;
use App\Http\Controllers\Api\V1\PedidoController;
use App\Http\Controllers\Api\V1\CartController;

Route::prefix('v1')->group(function () {
    
    // * RUTAS PÚBLICAS (Sin autenticación)
    
    // Autenticación
    Route::controller(AuthController::class)->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
        Route::post('password/email', 'sendPasswordResetEmail');
    });
    
    // Productos públicos
    Route::get('products', [ProductController::class, 'index']);
    Route::get('productos', [ProductController::class, 'index']);
    Route::post('productos', [ProductController::class, 'store']); // <-- MOVER AQUÍ

    Route::get('productos/destacados', [ProductController::class, 'featured']);
    Route::get('productos/{id}', [ProductController::class, 'show']);
    Route::get('categorias', [ProductController::class, 'categories']);
    
    // * RUTAS PRIVADAS (Requieren autenticación JWT)
    Route::middleware('jwt.auth')->group(function () {
        
        // Gestión de cuenta del usuario
        Route::controller(AuthController::class)->group(function () {
            Route::post('logout', 'logout');            // Cerrar sesión
            Route::get('me', 'me');                     // Obtener datos del usuario
            Route::put('profile', 'updateProfile');     // Actualizar perfil completo
            Route::patch('profile', 'updateProfile');   // Actualizar campos específicos
            Route::patch('me/password', 'changePassword'); // Cambiar contraseña
            Route::patch('me/deactivate', 'deactivateAccount'); // Desactivar cuenta
        });

         // Gestión de direcciones del usuario
        Route::controller(DireccionesController::class)->group(function () {
            Route::get('/me/addresses', 'index');                          // Listar direcciones
            Route::post('addresses', 'store');                         // Crear dirección
            Route::get('addresses/default', 'getDefault');              // Obtener predeterminada
            Route::get('addresses/{id}', 'show');                      // Obtener dirección específica
            Route::put('addresses/{id}', 'update');                    // Actualizar dirección
            Route::patch('addresses/{id}', 'update');                  // Actualizar dirección parcial
            Route::delete('addresses/{id}', 'destroy');                // Eliminar dirección
            Route::patch('addresses/{id}/set-default', 'setAsDefault'); // Establecer como predeterminada
        });
        
        // Pagos (requiere autenticación)
        Route::post('create-preference', [MercadoPagoController::class, 'createPreference']);
        
        // Gestión de pagos del usuario
        Route::controller(PagosController::class)->prefix('payments')->group(function () {
            Route::get('methods', 'getPaymentMethods');          // Métodos de pago activos
            Route::get('/', 'getUserPayments');                 // Historial de pagos del usuario
            Route::post('/', 'store');                          // Crear nuevo pago
            Route::post('confirm', 'confirmPayment');           // Confirmar pago (webhook MP)
            Route::get('/{id}', 'show');                        // Detalles de pago específico
            Route::patch('/{id}/status', 'updateStatus');       // Actualizar estado de pago
        });
        
        // Gestión de pedidos del usuario
        Route::controller(PedidoController::class)->prefix('orders')->group(function () {
            Route::get('/', 'index');                           // Listar pedidos del usuario
            Route::post('/', 'store');                          // Crear nuevo pedido
            Route::get('/{id}', 'show');                        // Detalles de pedido específico
            Route::patch('/{id}/status', 'updateStatus');       // Actualizar estado de pedido
            Route::patch('/{id}/cancel', 'cancel');             // Cancelar pedido
            Route::get('/{id}/payment', [PagosController::class, 'getPaymentByOrder']); // Info de pago del pedido
        });
    });

    // * RUTAS DE ADMINISTRADOR (JWT + Admin)
    
    Route::middleware(['jwt.auth', 'admin'])->group(function () {
        
        // Gestión de usuarios (admin)
        Route::controller(AuthController::class)->prefix('admin')->group(function () {
            Route::get('dashboard', 'adminDashboard');                    // Dashboard con estadísticas
            Route::get('users', 'getAllUsers');                           // Listar todos los usuarios con filtros
            Route::get('users/deactivated', 'getDeactivatedUsers');       // Listar usuarios desactivados
            Route::get('users/search', 'searchUsers');                    // Búsqueda avanzada de usuarios
            Route::get('users/statistics', 'getUserStatistics');          // Estadísticas detalladas
            Route::get('users/{id}', 'getUserDetails');                   // Obtener detalles de usuario
            Route::patch('users/{id}/reactivate', 'reactivateUser');      // Reactivar usuario
            Route::patch('users/{id}/deactivate', 'adminDeactivateUser'); // Desactivar usuario como admin
        });

        // Gestión de productos (admin)
        Route::controller(ProductController::class)->group(function () {
            Route::post('products', 'store');
            Route::get('products/{id}', 'show');
            Route::patch('products/{id}', 'update');
            Route::delete('products/{id}', 'destroy');
        });

         // Gestión de direcciones (admin)
        Route::controller(DireccionesController::class)->group(function () {
            Route::get('admin/users/{userId}/addresses', 'getUserAddresses');     // Direcciones de un usuario
            Route::get('admin/addresses/statistics', 'getAddressStatistics');     // Estadísticas de direcciones
        });
        
        // Gestión de pagos (admin)
        Route::controller(PagosController::class)->prefix('admin/payments')->group(function () {
            Route::get('/', 'getAllPayments');                  // Todos los pagos con filtros
            Route::get('/statistics', 'getPaymentStatistics');  // Estadísticas de pagos
            Route::patch('/{id}/status', 'adminUpdateStatus');  // Actualizar estado como admin
        });
        
        // Gestión de pedidos (admin)
        Route::controller(PedidoController::class)->prefix('admin/orders')->group(function () {
            Route::get('/', 'getAllOrders');                    // Todos los pedidos con filtros
            Route::get('/statistics', 'getOrderStatistics');    // Estadísticas de pedidos
            Route::patch('/{id}/status', 'adminUpdateStatus');  // Actualizar estado como admin
        });
    });

    // * WEBHOOKS (Sin autenticación)  
    Route::post('mercadopago/notifications', [MercadoPagoController::class, 'handleWebhook'])
        ->name('mercadopago.notifications');

    // Rutas de carrito
    Route::get('carritos/usuario/{userId}', [CartController::class, 'show']);
    Route::post('carritos', [CartController::class, 'store']);
    Route::post('carritos/{cartId}/items', [CartController::class, 'addItem']);
    Route::put('carritos/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('carritos/items/{itemId}', [CartController::class, 'deleteItem']);
    Route::patch('carritos/{cartId}', [CartController::class, 'empty']);

    // Carrito de compras
    Route::middleware('auth:api')->group(function () {
        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart', [CartController::class, 'store']);
        Route::put('cart/{id}', [CartController::class, 'update']);
        Route::delete('cart/{id}', [CartController::class, 'destroy']);
    });
});

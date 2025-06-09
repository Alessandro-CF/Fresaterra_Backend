<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MercadoPagoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\DireccionesController;
use App\Http\Controllers\Api\V1\Admin\ProductosController;
use App\Http\Controllers\Api\V1\Admin\CategoriasController;
use App\Http\Controllers\Api\V1\Admin\InventarioController;

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
        Route::controller(ProductosController::class)->prefix('admin')->group(function () {
            Route::get('products', 'index');                           // Listar todos los productos
            Route::post('products', 'store');                          // Crear nuevo producto
            Route::get('products/low-stock', 'lowStock');              // Productos con bajo inventario
            Route::get('products/{id}', 'show');                       // Mostrar producto específico
            Route::put('products/{id}', 'update');                     // Actualizar producto completo
            Route::patch('products/{id}', 'partialUpdate');            // Actualización parcial
            Route::patch('products/{id}/status', 'updateStatus');      // Cambiar estado
            Route::delete('products/{id}', 'destroy');                 // Eliminar producto
        });

        // Gestión de categorías (admin)
        Route::controller(CategoriasController::class)->prefix('admin')->group(function () {
            Route::get('categories', 'index');                         // Listar todas las categorías
            Route::post('categories', 'store');                        // Crear nueva categoría
            Route::get('categories/statistics', 'statistics');         // Estadísticas de categorías
            Route::get('categories/{id}', 'show');                     // Mostrar categoría específica
            Route::put('categories/{id}', 'update');                   // Actualizar categoría completa
            Route::patch('categories/{id}', 'partialUpdate');          // Actualización parcial
            Route::delete('categories/{id}', 'destroy');               // Eliminar categoría
        });

        // Gestión de inventario (admin)
        Route::controller(InventarioController::class)->prefix('admin')->group(function () {
            Route::get('inventory/products', 'getProductsInventory');          // Listar inventario de productos
            Route::get('inventory/products/{id}', 'getProductInventory');      // Detalle inventario producto
            Route::get('inventory/statistics', 'getStatistics');               // Estadísticas de inventario
            Route::post('inventory', 'store');                                 // Crear registro inventario
            Route::patch('inventory/products/{id}/stock', 'updateStock');      // Actualizar stock
            Route::patch('inventory/products/{id}/status', 'updateStatus');    // Cambiar estado inventario
        });

         // Gestión de direcciones (admin)
        Route::controller(DireccionesController::class)->group(function () {
            Route::get('admin/users/{userId}/addresses', 'getUserAddresses');     // Direcciones de un usuario
            Route::get('admin/addresses/statistics', 'getAddressStatistics');     // Estadísticas de direcciones
        });
    });

    // * WEBHOOKS (Sin autenticación)  
    Route::post('mercadopago/notifications', [MercadoPagoController::class, 'handleWebhook'])
        ->name('mercadopago.notifications');
});

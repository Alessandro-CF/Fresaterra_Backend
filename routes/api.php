<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\MercadoPagoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;

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
        
        // Pagos (requiere autenticación)
        Route::post('create-preference', [MercadoPagoController::class, 'createPreference']);
    });

    // * RUTAS DE ADMINISTRADOR (JWT + Admin)
    
    Route::middleware(['jwt.auth', 'admin'])->group(function () {

        // Gestión de productos (solo admin)
        Route::controller(ProductController::class)->group(function () {
            Route::post('products', 'store');
            Route::get('products/{id}', 'show');
            Route::patch('products/{id}', 'update');
            Route::delete('products/{id}', 'destroy');
        });

        // Gestión de usuarios (solo admin)
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
    });

    // * WEBHOOKS (Sin autenticación)  
    Route::post('mercadopago/notifications', [MercadoPagoController::class, 'handleWebhook'])
        ->name('mercadopago.notifications');
});

<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\ComentarioController;

use App\Http\Middleware\IsUserAuth;
use App\Http\Middleware\IsAdmin;
use App\Http\Controllers\WelcomeController;

// Ruta para la página de bienvenida
Route::get('api', [WelcomeController::class, 'index']);

/**//* Rutas con el prefijo /api/v1 */

Route::prefix('v1')->group(function () {
    //* PUBLIC ROUTES
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::get('products', [ProductController::class, 'index']);
    
    // Servicios públicos para probar API
    Route::get('comments', [ComentarioController::class, 'index']);
    Route::get('comments/{id}', [ComentarioController::class, 'show']);
    Route::get('notifications', [NotificacionController::class, 'index']);
    Route::get('notifications/{notificacion}', [NotificacionController::class, 'show']);

    // * PRIVATE ROUTES
    // Para los usuarios autenticados
    Route::middleware([IsUserAuth::class])->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::post('logout', 'logout');
            Route::get('me', 'getUser');
        });
    });

    // Para los administradores
    Route::middleware([IsAdmin::class])->group(function () {
        Route::controller(ProductController::class)->group(function () {
            Route::post('products', 'store');
            Route::get('/products/{id}', 'show');
            Route::patch('/products/{id}', 'update');
            Route::delete('/products/{id}', 'destroy');
        });
    });
    
    // Rutas para notificaciones
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificacionController::class, 'index']);
        Route::post('/send', [NotificacionController::class, 'store']);
        Route::get('/{notificacion}', [NotificacionController::class, 'show']);
        Route::put('/{notificacion}', [NotificacionController::class, 'update']);
        Route::patch('/{notificacion}', [NotificacionController::class, 'update']); // Añadir soporte para PATCH
        
        // Rutas específicas para notificaciones tipo campanita
        Route::get('/user/all', [NotificacionController::class, 'getUserNotifications']);
        Route::get('/user/unread', [NotificacionController::class, 'getUnreadNotifications']);
        Route::post('/notify-with-message', [NotificacionController::class, 'notificarConMensaje']);
        Route::patch('/{id}/read', [NotificacionController::class, 'markAsRead']);
        Route::patch('/read-all', [NotificacionController::class, 'markAllAsRead']);
        Route::delete('/{notificacion}', [NotificacionController::class, 'destroy']);
    });
    
    // Rutas para comentarios
    Route::prefix('comments')->group(function () {
        Route::get('/', [ComentarioController::class, 'index']);
        Route::post('/', [ComentarioController::class, 'store']);
        Route::get('/users', [ComentarioController::class, 'usuarios']);
        Route::get('/{id}', [ComentarioController::class, 'show']);
        Route::post('/{id}', [ComentarioController::class, 'update']); // Permitir POST para actualizar
        Route::patch('/{id}', [ComentarioController::class, 'update']); // Añadir soporte para PATCH
        Route::delete('/{id}', [ComentarioController::class, 'destroy']);
    });
});

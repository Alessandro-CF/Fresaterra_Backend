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
    // Ruta específica para el login de administradores
    Route::post('admin/login', [AuthController::class, 'login']);

    Route::get('products', [ProductController::class, 'index']);
    
    // Servicios públicos para probar API
    Route::get('comments', [ComentarioController::class, 'index']);
    Route::get('comments/{id}', [ComentarioController::class, 'show']);

   

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
    
    // Rutas para notificaciones de usuario
    Route::middleware([IsUserAuth::class])->group(function () {
        Route::prefix('me/notificaciones')->group(function () {
            Route::get('/', [NotificacionController::class, 'getUserNotifications']);
            Route::get('/unread', [NotificacionController::class, 'getUnreadNotifications']); // Ruta faltante
            Route::delete('/clean-email', [NotificacionController::class, 'cleanEmailNotifications']); // Nueva ruta
            Route::patch('/{id_notificacion}', [NotificacionController::class, 'updateUserNotificationStatus']);
            Route::delete('/{id_notificacion}', [NotificacionController::class, 'deleteUserNotification']);
        });
    });

    // Rutas para notificaciones de administrador
    Route::middleware([IsAdmin::class])->group(function () {
        Route::prefix('admin/notificaciones')->group(function () {
            Route::post('/', [NotificacionController::class, 'sendAdminNotification']);
            Route::get('/', [NotificacionController::class, 'getAllAdminNotifications']);
            Route::delete('/{id_notificacion}', [NotificacionController::class, 'deleteAdminNotification']);
        });
    });

    // Rutas API adicionales para NotificationService (protegidas)
    Route::middleware([IsAdmin::class])->group(function () {
        Route::prefix('admin/notificaciones')->group(function () {
            Route::post('/send-complete', [NotificacionController::class, 'apiSendCompleteNotification']);
            Route::post('/from-message', [NotificacionController::class, 'apiCreateNotificationFromMessage']);
            Route::post('/notify-multiple', [NotificacionController::class, 'apiNotifyMultipleUsers']);
            Route::post('/notify-by-event', [NotificacionController::class, 'apiNotifyByEvent']);
        });
    });

    // Rutas para confirmaciones de registro por email (solo administradores)
    Route::middleware([IsAdmin::class])->group(function () {
        Route::prefix('admin/emails')->group(function () {
            // Envío de emails de confirmación de registro
            Route::post('test', [NotificacionController::class, 'sendTestEmail']);
            Route::post('registration-confirmation', [NotificacionController::class, 'sendRegistrationConfirmation']);
            Route::post('registration-confirmation/multiple', [NotificacionController::class, 'sendMultipleRegistrationConfirmations']);
            Route::post('registration-confirmation/all', [NotificacionController::class, 'sendConfirmationToAllUsers']);
        });
        
        Route::prefix('admin/users')->group(function () {
            // Obtener lista de usuarios registrados (acceso administrativo)
            Route::get('registered', [NotificacionController::class, 'getRegisteredUsers']);
        });
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

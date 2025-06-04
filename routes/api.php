<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\MercadoPagoController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\AddressController;

use App\Http\Middleware\IsUserAuth;
use App\Http\Middleware\IsAdmin;

/**//* Rutas con el prefijo /api/v1 */

Route::prefix('v1')->group(function () {
    //* PUBLIC ROUTES
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::get('products', [ProductController::class, 'index']);

    // * PRIVATE ROUTES
    // Para los usuarios autenticados
    Route::middleware([IsUserAuth::class])->group(function () {
        Route::controller(AuthController::class)->group(function () {
            Route::post('logout', 'logout');
            Route::get('me', 'getUser');
        });

        // Rutas para gestión de direcciones
        Route::controller(AddressController::class)->group(function () {
            Route::get('addresses', 'index');              // Obtener todas las direcciones del usuario
            Route::post('addresses', 'store');             // Crear nueva dirección
            Route::patch('addresses/{id}/set-default', 'setDefault'); // Establecer como predeterminada
            Route::get('addresses/default', 'getDefault'); // Obtener dirección predeterminada
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

    // Ruta para crear una preferencia de pago con Mercado Pago
    Route::post('/create-preference', [MercadoPagoController::class, 'createPreference']);

    Route::post('/mercadopago/notifications', [MercadoPagoController::class, 'handleWebhook'])
    ->name('mercadopago.notifications'); // El nombre es importante para la función route()
});

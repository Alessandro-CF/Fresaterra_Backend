<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProductController;

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
});

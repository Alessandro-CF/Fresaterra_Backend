<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductoController;
use App\Http\Controllers\CarritoController;


Route::prefix('cart/api/v1')->group(function () {
    Route::get('/', [CarritoController::class, 'index']);
    Route::post('/', [CarritoController::class, 'store']);
    Route::get('/{id}', [CarritoController::class, 'show']);
    Route::put('/{id}', [CarritoController::class, 'update']);
});

Route::apiResource('productos', ProductoController::class)->parameters([
    'productos' => 'producto' // Para mantener tu convención de singular
]);

// Si necesitas autenticación
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});

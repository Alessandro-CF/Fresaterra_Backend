<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ProductoController;
use App\Http\Controllers\CarritoController;
Route::get('/', function () {
    return view('welcome');
});
Route::apiResource('producto', ProductoController::class);

Route::prefix('cart/api/v1')->group(function () {
    Route::get('/', [CarritoController::class, 'index']);
    Route::post('/', [CarritoController::class, 'store']);
    Route::get('/{id}', [CarritoController::class, 'show']);
    Route::put('/{id}', [CarritoController::class, 'update']);
});
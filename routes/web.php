<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

Route::get('/', function () {
    return view('welcome');
});

// Ruta para servir imágenes de storage
Route::get('/storage/{path}', function ($path) {
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    
    $file = Storage::disk('public')->get($path);
    $fullPath = Storage::disk('public')->path($path);
    $mimeType = mime_content_type($fullPath) ?: 'application/octet-stream';
    
    return response($file, 200)
        ->header('Content-Type', $mimeType)
        ->header('Cache-Control', 'public, max-age=3600');
})->where('path', '.*');

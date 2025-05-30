<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id_producto';
    public $timestamps = false; // Ya que usas fecha_creacion manual
    
    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'url_imagen',
        'estado',
        'peso'
    ];
    
    protected $casts = [
        'precio' => 'decimal:2',
        'peso' => 'decimal:2',
        'fecha_creacion' => 'datetime'
    ];
}
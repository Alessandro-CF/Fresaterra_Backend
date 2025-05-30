<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarritoItem extends Model
{
     protected $primaryKey = 'id_carrito_items';
    public $timestamps = false;

    protected $fillable = ['cantidad', 'carritos_id_carrito', 'productos_id_producto'];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'productos_id_producto', 'id_producto');
    }
}
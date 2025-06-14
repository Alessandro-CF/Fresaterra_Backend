<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $table = 'carrito_items';
    protected $primaryKey = 'id_carrito_items';
    public $timestamps = false;

    protected $fillable = [
        'cantidad',
        'carritos_id_carrito',
        'productos_id_producto'
    ];

    protected $with = ['producto'];

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Cart::class, 'carritos_id_carrito', 'id_carrito');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productos_id_producto', 'id_producto');
    }

    public function getSubtotal(): float
    {
        return $this->cantidad * $this->producto->precio;
    }
} 
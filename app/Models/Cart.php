<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $table = 'carritos';
    protected $primaryKey = 'id_carrito';
    public $timestamps = false;

    protected $fillable = [
        'estado',
        'usuarios_id_usuario'
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $with = ['items.producto'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuarios_id_usuario', 'id_usuario');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class, 'carritos_id_carrito', 'id_carrito');
    }

    public function getTotal(): float
    {
        return $this->items->sum(function ($item) {
            return $item->cantidad * $item->producto->precio;
        });
    }

    public function vaciar(): void
    {
        $this->items()->delete();
        $this->estado = 'vacio';
        $this->save();
    }

    public function scopeActivo($query)
    {
        return $query->where('estado', 'activo');
    }
} 
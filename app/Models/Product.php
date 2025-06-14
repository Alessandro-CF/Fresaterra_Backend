<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'id_producto';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'precio',
        'url_imagen',
        'estado',
        'peso',
        'categorias_id_categoria',
        'fecha_creacion'
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'precio' => 'decimal:2'
    ];

    protected $appends = ['url_imagen_completa'];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $with = ['categoria'];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categorias_id_categoria', 'id_categoria');
    }

    public function carritoItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'productos_id_producto', 'id_producto');
    }

    public function getUrlImagenCompletaAttribute(): ?string
    {
        return $this->url_imagen ? Storage::url($this->url_imagen) : null;
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'disponible');
    }

    public function scopeBuscar($query, $texto)
    {
        if ($texto) {
            return $query->where(function($q) use ($texto) {
                $q->where('nombre', 'LIKE', "%{$texto}%")
                  ->orWhere('descripcion', 'LIKE', "%{$texto}%");
            });
        }
        return $query;
    }

    public function scopeCategoria($query, $categoriaId)
    {
        if ($categoriaId) {
            return $query->where('categorias_id_categoria', $categoriaId);
        }
        return $query;
    }

    public function scopeDestacados($query)
    {
        return $query->activos()->inRandomOrder()->take(6);
    }
} 
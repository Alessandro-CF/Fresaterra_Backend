<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categorias';
    protected $primaryKey = 'id_categoria';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime'
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function productos(): HasMany
    {
        return $this->hasMany(Product::class, 'categorias_id_categoria', 'id_categoria');
    }
} 
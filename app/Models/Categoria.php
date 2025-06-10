<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Categoria
 * 
 * @property int $id_categoria
 * @property string $nombre
 * @property string $descripcion
 * @property Carbon $fecha_creacion
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * 
 * @property Collection|Producto[] $productos
 *
 * @package App\Models
 */
class Categoria extends Model
{
    protected $table = 'categorias';
    protected $primaryKey = 'id_categoria';

    protected $casts = [
        'fecha_creacion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $fillable = [
        'nombre',
        'descripcion',
        'fecha_creacion'
    ];

    // * MÉTODOS HELPER

    /**
     * Obtener el número total de productos en esta categoría
     */
    public function getTotalProductosAttribute()
    {
        return $this->productos()->count();
    }

    /**
     * Obtener productos activos de la categoría
     */
    public function getProductosActivosAttribute()
    {
        return $this->productos()->where('estado', 'activo')->count();
    }

    /**
     * Scope para obtener categorías con productos
     */
    public function scopeWithProducts($query)
    {
        return $query->whereHas('productos');
    }

    /**
     * Scope para obtener categorías con productos activos
     */
    public function scopeWithActiveProducts($query)
    {
        return $query->whereHas('productos', function ($query) {
            $query->where('estado', 'activo');
        });
    }

    // * RELACIONES

    /**
     * Relación con productos - Una categoría tiene muchos productos
     */
    public function productos()
    {
        return $this->hasMany(Producto::class, 'categorias_id_categoria');
    }
}

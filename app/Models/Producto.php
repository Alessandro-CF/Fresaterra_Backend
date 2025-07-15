<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Added
use Illuminate\Database\Eloquent\Relations\HasMany;   // Added
use Illuminate\Support\Facades\Storage;             // Added

/**
 * Class Producto
 * 
 * @property int $id_producto
 * @property string $nombre
 * @property string $descripcion
 * @property float $precio // Will be cast to decimal:2
 * @property string $url_imagen
 * @property string $estado
 * @property string $peso
 * @property Carbon $fecha_creacion
 * @property int $categorias_id_categoria // Added from Product.php fillable, ensure it's in $fillable
 * 
 * @property Categoria $categoria // Changed Category to Categoria
 * @property Collection|CarritoItems[] $carrito_items // Changed from CarritoItem to CarritoItems
 * @property Collection|Inventario[] $inventarios
 * @property Collection|PedidoItems[] $pedido_items // Changed from PedidoItem to PedidoItems
 * @property Collection|Comentario[] $comentarios
 * @property-read string|null $url_imagen_completa // Added for accessor
 *
 * @package App\Models
 */
class Producto extends Model
{
	protected $table = 'productos';
	protected $primaryKey = 'id_producto';
	public $timestamps = true; // Enable timestamps to track changes

	protected $casts = [
		'precio' => 'decimal:2', // Changed from float to decimal:2
		'fecha_creacion' => 'datetime',
		'categorias_id_categoria' => 'int', // Ensure this is cast if it's an ID
        'peso' => 'string'
	];
    
    // Definir campos que deben tratarse como atributos
    protected $attributes = [
        'estado' => 'activo', // Valor predeterminado para 'estado'
    ];

	protected $fillable = [
		'nombre',
		'descripcion',
		'precio',
		'url_imagen',
		'estado',
		'peso',
		'fecha_creacion',
		'categorias_id_categoria'
	];

    protected $appends = ['url_imagen_completa', 'imagen_url', 'inventario_info', 'en_stock', 'cantidad_disponible']; // Added from Product.php

    // Don't hide timestamps anymore - we want to track them
    protected $hidden = [];
    
    // Define custom column names for timestamps if needed
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $with = ['categoria']; // Added from Product.php

	// Relationships from Producto.php (adapted)
	public function carrito_items(): HasMany // Kept original name, ensured CarritoItems
	{
		return $this->hasMany(CarritoItems::class, 'productos_id_producto', 'id_producto');
	}

	public function inventarios(): HasMany
	{
		return $this->hasMany(Inventario::class, 'productos_id_producto', 'id_producto');
	}

	public function pedido_items(): HasMany
	{
		return $this->hasMany(PedidoItems::class, 'productos_id_producto', 'id_producto');
	}

	/**
	 * Relación con categoría - Un producto pertenece a una categoría
	 */
	public function categoria(): BelongsTo // Adapted from Product.php to use Category
	{
		return $this->belongsTo(Categoria::class, 'categorias_id_categoria', 'id_categoria'); // Changed Category to Categoria
	}

	/**
	 * Relación con comentarios - Un producto tiene muchos comentarios
	 */
	public function comentarios(): HasMany
	{
		return $this->hasMany(Comentario::class, 'productos_id_producto', 'id_producto');
	}

	// Accessors from Producto.php
	/**
	 * Obtener el promedio de calificaciones
	 */
	public function getAverageRatingAttribute(): float
	{
		return $this->comentarios()->avg('calificacion') ?? 0;
	}

	/**
	 * Obtener el total de reseñas
	 */
	public function getTotalReviewsAttribute(): int
	{
		return $this->comentarios()->count();
	}

	/**
	 * Obtener información del inventario actual
	 */
	public function getInventarioInfoAttribute(): array
	{
		$inventario = $this->inventarios()->first();
		
		return [
			'cantidad_disponible' => $inventario ? $inventario->cantidad_disponible : 0,
			'estado_inventario' => $inventario ? $inventario->estado : 'agotado',
			'fecha_ingreso' => $inventario ? $inventario->fecha_ingreso : null,
			'ultima_actualizacion' => $inventario ? $inventario->ultima_actualizacion : null,
			'en_stock' => $inventario ? ($inventario->cantidad_disponible > 0 && $inventario->estado === 'disponible') : false
		];
	}

	/**
	 * Verificar si el producto tiene stock disponible
	 */
	public function getEnStockAttribute(): bool
	{
		$inventario = $this->inventarios()->first();
		return $inventario ? ($inventario->cantidad_disponible > 0 && $inventario->estado === 'disponible') : false;
	}

	/**
	 * Obtener la cantidad disponible en inventario
	 */
	public function getCantidadDisponibleAttribute(): int
	{
		$inventario = $this->inventarios()->first();
		return $inventario ? $inventario->cantidad_disponible : 0;
	}

    // Accessor from Product.php
    public function getUrlImagenCompletaAttribute(): ?string
    {
        return $this->url_imagen ? url(Storage::url($this->url_imagen)) : null;
    }

    // Accessor para compatibilidad con frontend
    public function getImagenUrlAttribute(): ?string
    {
        return $this->getUrlImagenCompletaAttribute();
    }

    // Scopes from Product.php
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo'); // Assuming 'activo' is the active state
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
        return $query->activos()
            ->withAvg('comentarios', 'calificacion')
            ->withCount('comentarios')
            ->havingRaw('comentarios_avg_calificacion >= 4.0 OR comentarios_count = 0')
            ->orderByRaw('COALESCE(comentarios_avg_calificacion, 0) DESC')
            ->orderBy('comentarios_count', 'desc')
            ->orderBy('fecha_creacion', 'desc')
            ->take(4);
    }

    /**
     * Boot method to set default fecha_creacion if not present
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($producto) {
            if (empty($producto->fecha_creacion)) {
                $producto->fecha_creacion = now();
            }
        });
    }
}

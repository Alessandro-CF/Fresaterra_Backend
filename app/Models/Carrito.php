<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class Carrito
 * 
 * @property int $id_carrito
 * @property string $estado
 * @property Carbon $fecha_creacion
 * @property int $usuarios_id_usuario
 * 
 * @property User $usuario
 * @property \Illuminate\Database\Eloquent\Collection|CartItem[] $items
 *
 * @package App\Models
 */
class Carrito extends Model
{
	protected $table = 'carritos';
	protected $primaryKey = 'id_carrito';
	public $timestamps = false;

	protected $casts = [
		'fecha_creacion' => 'datetime',
		'usuarios_id_usuario' => 'int'
	];

	protected $fillable = [
		'usuarios_id_usuario',
		'estado'
	];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $with = ['items'];

	// Estados disponibles para carritos
	const ESTADO_ACTIVO = 'activo';
	const ESTADO_ABANDONADO = 'abandonado';
	const ESTADO_CONVERTIDO = 'convertido';

	/**
	 * Obtener todos los estados disponibles
	 */
	public static function getEstados()
	{
		return [
			self::ESTADO_ACTIVO,
			self::ESTADO_ABANDONADO,
			self::ESTADO_CONVERTIDO,
		];
	}

	public function usuario(): BelongsTo
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario', 'id_usuario');
	}

	public function items(): HasMany
	{
		return $this->hasMany(CarritoItems::class, 'carritos_id_carrito', 'id_carrito');
	}

    public function getTotal(): float
    {
        return $this->items->sum(function ($item) {
            return $item->getSubtotal();
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

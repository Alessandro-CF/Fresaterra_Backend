<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Carrito
 * 
 * @property int $id_carrito
 * @property string $estado
 * @property Carbon $fecha_creacion
 * @property int $usuarios_id_usuario
 * 
 * @property Usuario $usuario
 * @property Collection|CarritoItem[] $carrito_items
 *
 * @package App\Models
 */
class Carrito extends Model
{
	protected $table = 'carritos';
	protected $primaryKey = 'id_carrito';

	protected $casts = [
		'fecha_creacion' => 'datetime',
		'usuarios_id_usuario' => 'int'
	];

	protected $fillable = [
		'estado',
		'fecha_creacion',
		'usuarios_id_usuario'
	];

	public function usuario()
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario');
	}

	public function carrito_items()
	{
		return $this->hasMany(CarritoItems::class, 'carritos_id_carrito');
	}
}

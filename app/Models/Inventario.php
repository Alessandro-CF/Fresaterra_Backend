<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Inventario
 * 
 * @property int $id_inventario
 * @property int $cantidad_disponible
 * @property Carbon $fecha_ingreso
 * @property Carbon $ultima_actualizacion
 * @property string $estado
 * @property int $productos_id_producto
 * 
 * @property Producto $producto
 *
 * @package App\Models
 */
class Inventario extends Model
{
	protected $table = 'inventarios';
	protected $primaryKey = 'id_inventario';

	protected $casts = [
		'cantidad_disponible' => 'int',
		'fecha_ingreso' => 'datetime',
		'ultima_actualizacion' => 'datetime',
		'productos_id_producto' => 'int'
	];

	protected $fillable = [
		'cantidad_disponible',
		'fecha_ingreso',
		'ultima_actualizacion',
		'estado',
		'productos_id_producto'
	];

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'productos_id_producto');
	}
}

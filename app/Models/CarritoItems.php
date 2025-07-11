<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class CarritoItem
 * 
 * @property int $id_carrito_items
 * @property int|null $cantidad
 * @property int $carritos_id_carrito
 * @property int $productos_id_producto
 * 
 * @property Carrito $carrito
 * @property Producto $producto
 *
 * @package App\Models
 */
class CarritoItems extends Model
{
	protected $table = 'carrito_items';
	protected $primaryKey = 'id_carrito_items';

	protected $casts = [
		'cantidad' => 'int',
		'carritos_id_carrito' => 'int',
		'productos_id_producto' => 'int'
	];

	protected $fillable = [
		'cantidad',
		'carritos_id_carrito',
		'productos_id_producto'
	];

	public function carrito()
	{
		return $this->belongsTo(Carrito::class, 'carritos_id_carrito');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'productos_id_producto');
	}
}

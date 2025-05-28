<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Producto
 * 
 * @property int $id_producto
 * @property string $nombre
 * @property string $descripcion
 * @property float $precio
 * @property string $url_imagen
 * @property string $estado
 * @property string $peso
 * @property Carbon $fecha_creacion
 * 
 * @property Collection|CarritoItem[] $carrito_items
 * @property Collection|Inventario[] $inventarios
 * @property Collection|PedidoItem[] $pedido_items
 *
 * @package App\Models
 */
class Producto extends Model
{
	protected $table = 'productos';
	protected $primaryKey = 'id_producto';

	protected $casts = [
		'precio' => 'float',
		'fecha_creacion' => 'datetime'
	];

	protected $fillable = [
		'nombre',
		'descripcion',
		'precio',
		'url_imagen',
		'estado',
		'peso',
		'fecha_creacion'
	];

	public function carrito_items()
	{
		return $this->hasMany(CarritoItems::class, 'productos_id_producto');
	}

	public function inventarios()
	{
		return $this->hasMany(Inventario::class, 'productos_id_producto');
	}

	public function pedido_items()
	{
		return $this->hasMany(PedidoItems::class, 'productos_id_producto');
	}
}

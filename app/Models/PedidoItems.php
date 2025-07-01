<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class PedidoItem
 * 
 * @property int $id_pedido_items
 * @property int $cantidad
 * @property float $precio
 * @property float $subtotal
 * @property int $pedidos_id_pedido
 * @property int $productos_id_producto
 * 
 * @property Pedido $pedido
 * @property Producto $producto
 *
 * @package App\Models
 */
class PedidoItems extends Model
{
	protected $table = 'pedido_items';
	protected $primaryKey = 'id_pedido_items';

	protected $casts = [
		'cantidad' => 'int',
		'precio' => 'float',
		'subtotal' => 'float',
		'pedidos_id_pedido' => 'int',
		'productos_id_producto' => 'int'
	];

	protected $fillable = [
		'cantidad',
		'precio',
		'subtotal',
		'pedidos_id_pedido',
		'productos_id_producto',
		// 🔧 Campos snapshot para preservar datos históricos
		'producto_nombre_snapshot',
		'producto_descripcion_snapshot',
		'producto_imagen_snapshot',
		'producto_peso_snapshot',
		'categoria_nombre_snapshot'
	];

	public function pedido()
	{
		return $this->belongsTo(Pedido::class, 'pedidos_id_pedido');
	}

	public function producto()
	{
		return $this->belongsTo(Producto::class, 'productos_id_producto');
	}
}

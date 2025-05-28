<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pago
 * 
 * @property int $id_pago
 * @property Carbon $fecha_pago
 * @property float $monto_pago
 * @property string $estado_pago
 * @property string $referencia_pago
 * @property int $pedidos_id_pedido
 * @property int $metodos_pago_id_metodo_pago
 * 
 * @property MetodosPago $metodos_pago
 * @property Pedido $pedido
 *
 * @package App\Models
 */
class Pago extends Model
{
	protected $table = 'pagos';
	protected $primaryKey = 'id_pago';

	protected $casts = [
		'fecha_pago' => 'datetime',
		'monto_pago' => 'float',
		'pedidos_id_pedido' => 'int',
		'metodos_pago_id_metodo_pago' => 'int'
	];

	protected $fillable = [
		'fecha_pago',
		'monto_pago',
		'estado_pago',
		'referencia_pago',
		'pedidos_id_pedido',
		'metodos_pago_id_metodo_pago'
	];

	public function metodos_pago()
	{
		return $this->belongsTo(MetodosPago::class, 'metodos_pago_id_metodo_pago');
	}

	public function pedido()
	{
		return $this->belongsTo(Pedido::class, 'pedidos_id_pedido');
	}
}

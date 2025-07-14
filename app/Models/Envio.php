<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Envio
 * 
 * @property int $id_envio
 * @property float $monto_envio
 * @property string $estado
 * @property Carbon $fecha_envio
 * @property int $transportistas_id_transportista
 * @property int $pedidos_id_pedido
 * 
 * @property Pedido $pedido
 * @property Transportista $transportista
 * @property Collection|Direccion[] $direcciones
 *
 * @package App\Models
 */
class Envio extends Model
{
	protected $table = 'envios';
	protected $primaryKey = 'id_envio';

	// Estados disponibles para envios
	const ESTADO_PENDIENTE = 'pendiente';
	const ESTADO_CONFIRMADO = 'confirmado';
	const ESTADO_PREPARANDO = 'preparando';
	const ESTADO_EN_CAMINO = 'en_camino';
	const ESTADO_ENTREGADO = 'entregado';
	const ESTADO_CANCELADO = 'cancelado';

	/**
	 * Obtener todos los estados disponibles
	 */
	public static function getEstados()
	{
		return [
			self::ESTADO_PENDIENTE,
			self::ESTADO_CONFIRMADO,
			self::ESTADO_PREPARANDO,
			self::ESTADO_EN_CAMINO,
			self::ESTADO_ENTREGADO,
			self::ESTADO_CANCELADO,
		];
	}

	protected $casts = [
		'monto_envio' => 'float',
		'fecha_envio' => 'datetime',
		'transportistas_id_transportista' => 'int',
		'pedidos_id_pedido' => 'int',
		'direcciones_id_direccion' => 'int'
	];

	protected $fillable = [
		'monto_envio',
		'estado',
		'fecha_envio',
		'transportistas_id_transportista',
		'pedidos_id_pedido',
		'direcciones_id_direccion',
		// 🔧 Campos snapshot optimizados para preservar datos históricos de dirección
		'direccion_linea1_snapshot',
		'direccion_linea2_snapshot',
		'direccion_ciudad_snapshot',
		'direccion_estado_snapshot',
		// 🔧 Campos snapshot optimizados para preservar datos históricos de transportista
		'transportista_nombre_snapshot',
		'transportista_telefono_snapshot'
	];

	public function pedido()
	{
		return $this->belongsTo(Pedido::class, 'pedidos_id_pedido');
	}

	public function transportista()
	{
		return $this->belongsTo(Transportista::class, 'transportistas_id_transportista');
	}

	public function direccion()
	{
		return $this->belongsTo(Direccion::class, 'direcciones_id_direccion');
	}
}

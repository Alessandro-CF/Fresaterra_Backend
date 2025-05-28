<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Pedido
 * 
 * @property int $id_pedido
 * @property float $monto_total
 * @property string $estado
 * @property Carbon $fecha_creacion
 * @property int $usuarios_id_usuario
 * 
 * @property Usuario $usuario
 * @property Collection|Envio[] $envios
 * @property Collection|Pago[] $pagos
 * @property Collection|PedidoItem[] $pedido_items
 *
 * @package App\Models
 */
class Pedido extends Model
{
	protected $table = 'pedidos';
	protected $primaryKey = 'id_pedido';

	protected $casts = [
		'monto_total' => 'float',
		'fecha_creacion' => 'datetime',
		'usuarios_id_usuario' => 'int'
	];

	protected $fillable = [
		'monto_total',
		'estado',
		'fecha_creacion',
		'usuarios_id_usuario'
	];

	public function usuario()
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario');
	}

	public function envios()
	{
		return $this->hasMany(Envio::class, 'pedidos_id_pedido');
	}

	public function pagos()
	{
		return $this->hasMany(Pago::class, 'pedidos_id_pedido');
	}

	public function pedido_items()
	{
		return $this->hasMany(PedidoItems::class, 'pedidos_id_pedido');
	}
}

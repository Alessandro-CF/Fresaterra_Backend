<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Notificacion
 * 
 * @property int $id_notificacion
 * @property string $estado
 * @property Carbon $fecha_creacion
 * @property int $usuarios_id_usuario
 * @property int $mensajes_id_mensaje
 * 
 * @property Mensaje $mensaje
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Notificacion extends Model
{
	protected $table = 'notificaciones';
	protected $primaryKey = 'id_notificacion';

	protected $casts = [
		'fecha_creacion' => 'datetime',
		'usuarios_id_usuario' => 'int',
		'mensajes_id_mensaje' => 'int'
	];

	protected $fillable = [
		'estado',
		'fecha_creacion',
		'usuarios_id_usuario',
		'mensajes_id_mensaje'
	];

	public function mensaje()
	{
		return $this->belongsTo(Mensaje::class, 'mensajes_id_mensaje');
	}

	public function usuario()
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario');
	}
}

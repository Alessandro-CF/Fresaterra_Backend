<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Mensaje
 * 
 * @property int $id_mensaje
 * @property string $tipo
 * @property string $contenido
 * 
 * @property Collection|Notificacion[] $notificaciones
 *
 * @package App\Models
 */
class Mensaje extends Model
{
	protected $table = 'mensajes';
	protected $primaryKey = 'id_mensaje';

	protected $fillable = [
		'tipo',
		'contenido'
	];

	public function notificaciones()
	{
		return $this->hasMany(Notificacion::class, 'mensajes_id_mensaje');
	}
}

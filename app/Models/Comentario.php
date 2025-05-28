<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Comentario
 * 
 * @property int $id_resena
 * @property int|null $calificacion
 * @property string|null $contenido
 * @property Carbon $fecha_creacion
 * @property int $usuarios_id_usuario
 * 
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Comentario extends Model
{
	protected $table = 'comentarios';
	protected $primaryKey = 'id_resena';
	public $timestamps = false;

	protected $casts = [
		'calificacion' => 'int',
		'fecha_creacion' => 'datetime',
		'usuarios_id_usuario' => 'int'
	];

	protected $fillable = [
		'calificacion',
		'contenido',
		'fecha_creacion',
		'usuarios_id_usuario'
	];

	public function usuario()
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario');
	}
}

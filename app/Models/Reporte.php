<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Reporte
 * 
 * @property int $id_reporte
 * @property string $tipo
 * @property string|null $archivo_url
 * @property Carbon $fecha_creacion
 * @property string $estado
 * @property string|null $fecha_inicio
 * @property string|null $fecha_fin
 * @property int $usuarios_id_usuario
 * 
 * @property Usuario $usuario
 *
 * @package App\Models
 */
class Reporte extends Model
{
	protected $table = 'reportes';
	protected $primaryKey = 'id_reporte';

	protected $casts = [
		'fecha_creacion' => 'datetime',
		'fecha_inicio' => 'date',
		'fecha_fin' => 'date',
		'usuarios_id_usuario' => 'int'
	];

	protected $fillable = [
		'tipo',
		'estado',
		'archivo_url',
		'fecha_creacion',
		'fecha_inicio',
		'fecha_fin',
		'usuarios_id_usuario'
	];

	public function usuario()
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario');
	}
}

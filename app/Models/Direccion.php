<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Direccione
 * 
 * @property int $id_direccion
 * @property string $calle
 * @property string $numero
 * @property string $distrito
 * @property string $ciudad
 * @property string|null $referencia
 * @property int $usuarios_id_usuario
 * @property int $envios_id_envio
 * 
 * @property Usuario $usuario
 * @property Envio $envio
 *
 * @package App\Models
 */
class Direccion extends Model
{
	protected $table = 'direcciones';
	protected $primaryKey = 'id_direccion';
	public $timestamps = false;

	protected $casts = [
		'usuarios_id_usuario' => 'int',
		'envios_id_envio' => 'int'
	];

	protected $fillable = [
		'calle',
		'numero',
		'distrito',
		'ciudad',
		'referencia',
		'usuarios_id_usuario',
		'envios_id_envio'
	];

	public function usuario()
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario');
	}

	public function envio()
	{
		return $this->belongsTo(Envio::class, 'envios_id_envio');
	}
}

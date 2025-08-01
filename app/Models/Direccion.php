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
 * @property string $predeterminada
 * @property int $usuarios_id_usuario
 * @property int $envios_id_envio
 * 
 * @property User $usuario
 * @property Envio $envio
 *
 * @package App\Models
 */
class Direccion extends Model
{
	protected $table = 'direcciones';
	protected $primaryKey = 'id_direccion';
	//public $timestamps = true;

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
		'predeterminada',
		'usuarios_id_usuario'
		// Temporalmente removido 'envios_id_envio' hasta implementar sistema de envíos
	];

	// * MÉTODOS HELPER

	/**
	 * Verificar si es la dirección predeterminada
	 */
	public function isDefault()
	{
		return $this->predeterminada === 'si';
	}

	/**
	 * Obtener la dirección formateada
	 */
	public function getFormattedAddressAttribute()
	{
		return "{$this->calle} {$this->numero}, {$this->distrito}, {$this->ciudad}";
	}

	/**
	 * Scope para obtener solo direcciones predeterminadas
	 */
	public function scopeDefault($query)
	{
		return $query->where('predeterminada', 'si');
	}


	// * Relaciones entre modelos

	/**
	 * Relación con el modelo Usuario
	 */
	public function usuario()
	{
		return $this->belongsTo(User::class, 'usuarios_id_usuario');
	}

	/**
	 * Relación con el modelo Envio
	 */
	public function envio()
	{
		return $this->belongsTo(Envio::class, 'envios_id_envio');
	}
}

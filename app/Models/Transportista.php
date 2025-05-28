<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Transportista
 * 
 * @property int $id_transportista
 * @property string $nombre
 * @property string $telefono
 * @property string $tipo_transporte
 * @property string|null $empresa
 * @property string $placa_vehiculo
 * 
 * @property Collection|Envio[] $envios
 *
 * @package App\Models
 */
class Transportista extends Model
{
	protected $table = 'transportistas';
	protected $primaryKey = 'id_transportista';

	protected $fillable = [
		'nombre',
		'telefono',
		'tipo_transporte',
		'empresa',
		'placa_vehiculo'
	];

	public function envios()
	{
		return $this->hasMany(Envio::class, 'transportistas_id_transportista');
	}
}

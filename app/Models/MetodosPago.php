<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class MetodosPago
 * 
 * @property int $id_metodo_pago
 * @property string $nombre
 * @property string $activo
 * @property Carbon $fecha_creacion
 * 
 * @property Collection|Pago[] $pagos
 *
 * @package App\Models
 */
class MetodosPago extends Model
{
	protected $table = 'metodos_pago';
	protected $primaryKey = 'id_metodo_pago';

	protected $casts = [
		'activo' => 'binary',
		'fecha_creacion' => 'datetime'
	];

	protected $fillable = [
		'nombre',
		'activo',
		'fecha_creacion'
	];

	public function pagos()
	{
		return $this->hasMany(Pago::class, 'metodos_pago_id_metodo_pago');
	}
}

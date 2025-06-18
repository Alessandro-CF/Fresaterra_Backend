<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Role
 * 
 * @property int $id_rol
 * @property string $nombre
 * @property string|null $descripcion
 * @property Carbon $fecha_creacion
 * 
 * @property Collection|Usuario[] $usuarios
 *
 * @package App\Models
 */
class Rol extends Model
{
	protected $table = 'roles';
	protected $primaryKey = 'id_rol';
	public $timestamps = true; // Usar created_at y updated_at
	
	protected $fillable = [
		'nombre',
		'descripcion'
	];

	public function usuarios()
	{
		return $this->hasMany(User::class, 'roles_id_rol');
	}
}

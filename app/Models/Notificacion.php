<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Notificacione
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
    
    /**
     * Generar un UUID para nuevas notificaciones
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = \Illuminate\Support\Str::uuid()->toString();
            }
        });
    }    protected $casts = [
        'fecha_creacion' => 'datetime',
        'read_at' => 'datetime',
        'usuarios_id_usuario' => 'int',
        'mensajes_id_mensaje' => 'int',
        'data' => 'array'
    ];

    protected $fillable = [
        'uuid',
        'type',
        'estado',
        'fecha_creacion',
        'read_at',
        'usuarios_id_usuario',
        'mensajes_id_mensaje',
        'data'
    ];

	public function mensaje()
	{
		return $this->belongsTo(Mensaje::class, 'mensajes_id_mensaje');
	}    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuarios_id_usuario');
    }
    
    /**
     * Marcar la notificación como leída
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->read_at = now();
            $this->save();
        }
        
        return $this;
    }
    
    /**
     * Marcar la notificación como no leída
     */
    public function markAsUnread()
    {
        if (!is_null($this->read_at)) {
            $this->read_at = null;
            $this->save();
        }
        
        return $this;
    }
    
    /**
     * Determinar si la notificación ha sido leída
     */
    public function isRead()
    {
        return $this->read_at !== null;
    }
    
    /**
     * Determinar si la notificación no ha sido leída
     */
    public function isUnread()
    {
        return $this->read_at === null;
    }
}

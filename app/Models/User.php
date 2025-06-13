<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'id_usuario';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
		'apellidos',
		'email',
		'password',
		'telefono',
		'roles_id_rol'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    //* Métodos para relacionar con otros modelos
    public function role()
	{
		return $this->belongsTo(Rol::class, 'roles_id_rol');
	}

	public function carritos()
	{
		return $this->hasMany(Carrito::class, 'usuarios_id_usuario');
	}

	public function comentarios()
	{
		return $this->hasMany(Comentario::class, 'usuarios_id_usuario');
	}

	public function direcciones()
	{
		return $this->hasMany(Direccion::class, 'usuarios_id_usuario');
	}

	public function notificaciones()
	{
		return $this->hasMany(Notificacion::class, 'usuarios_id_usuario');
	}

	public function pedidos()
	{
		return $this->hasMany(Pedido::class, 'usuarios_id_usuario');
	}

	public function reportes()
	{
		return $this->hasMany(Reporte::class, 'usuarios_id_usuario');
	}

    //* Métodos para JWT	

     /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}

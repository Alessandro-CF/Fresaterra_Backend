<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

   
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

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
		'estado',
		'fecha_creacion',
		'roles_id_rol',
        'provider',
        'provider_id',
        'avatar',
        'email_verified_at'
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
            'estado' => 'boolean',
        ];
    }


    //* Métodos para relacionar con otros modelos
    public function role(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'roles_id_rol', 'id_rol'); // Changed Role to Rol
    }

    public function carritos(): HasMany
    {
        return $this->hasMany(Carrito::class, 'usuarios_id_usuario', 'id_usuario'); // Changed Cart to Carrito
    }

    public function carritoActivo()
    {
        return $this->carritos()
            ->where('estado', 'activo')
            ->latest('fecha_creacion')
            ->first();
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

    //* Métodos para manejo de estado de cuenta
    
    /**
     * Verificar si el usuario está activo
     */
    public function isActive()
    {
        return $this->estado === true;
    }

    /**
     * Verificar si el usuario está desactivado
     */
    public function isDeactivated()
    {
        return $this->estado === false;
    }

    /**
     * Desactivar la cuenta del usuario
     */
    public function deactivate()
    {
        $this->update(['estado' => false]);
    }

    /**
     * Activar la cuenta del usuario
     */
    public function activate()
    {
        $this->update(['estado' => true]);
    }

    /**
     * Scope para obtener solo usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('estado', true);
    }

    /**
     * Scope para obtener solo usuarios desactivados
     */
    public function scopeDeactivated($query)
    {
        return $query->where('estado', false);
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

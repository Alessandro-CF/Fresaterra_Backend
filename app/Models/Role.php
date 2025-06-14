<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion'
    ];

    protected $casts = [
        'fecha_creacion' => 'datetime'
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'roles_id_rol', 'id_rol');
    }
} 
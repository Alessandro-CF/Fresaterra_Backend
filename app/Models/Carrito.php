<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrito extends Model
{
 protected $primaryKey = 'id_carrito';
    public $timestamps = false;

    protected $fillable = ['estado', 'fecha_creacion', 'usuarios_id_usuario'];

    public function items()
    {
        return $this->hasMany(CarritoItem::class, 'carritos_id_carrito', 'id_carrito');
    }
}
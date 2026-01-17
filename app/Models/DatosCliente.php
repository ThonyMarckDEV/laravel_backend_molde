<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatosCliente extends Model
{
    use HasFactory;

    protected $table = 'datos_clientes';

    protected $fillable = [
        'nombre', 
        'apellidoPaterno', 
        'apellidoMaterno', 
        'estadoCivil', 
        'sexo', 
        'dni', 
        'fechaNacimiento',
        'ruc'
    ];

    public function usuario() 
    {
        return $this->hasOne(User::class, 'datos_cliente_id');
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'datos_cliente_id');
    }
}
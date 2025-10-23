<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Datos extends Model
{
    use HasFactory;

    protected $table = 'datos';

    protected $fillable = [
        'nombre',
        'apellidoPaterno',
        'apellidoMaterno',
        'sexo',
        'dni'
    ];

    public function usuario()
    {
        return $this->hasOne(User::class, 'id_Datos', 'id');
    }

    public function contactos()
    {
        return $this->hasMany(Contacto::class, 'id_Datos' , 'id');
    }

}

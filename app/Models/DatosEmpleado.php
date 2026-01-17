<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DatosEmpleado extends Model
{
    use HasFactory;

    protected $table = 'datos_empleados';

    protected $fillable = [
        'nombre', 'apellidoPaterno', 'apellidoMaterno', 'estadoCivil',
        'sexo', 'dni', 'fechaNacimiento', 'direccion', 'telefono',
    ];

    public function usuario() {
        return $this->hasOne(User::class, 'datos_empleado_id');
    }
}
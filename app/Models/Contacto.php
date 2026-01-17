<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contacto extends Model
{
    use HasFactory;

    protected $table = 'contactos';

    protected $fillable = [
        'datos_cliente_id',
        'telefono',
        'correo'
    ];

    public function datosCliente()
    {
        // Ahora pertenece a DatosCliente
        return $this->belongsTo(DatosCliente::class, 'datos_cliente_id');
    }
}
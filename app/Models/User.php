<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * Los atributos que se pueden asignar de manera masiva.
     *
     * @var array<string>
     */
    protected $fillable = [
        'username', 
        'password', 
        'datos_cliente_id', 
        'datos_empleado_id', 
        'rol_id', 
        'estado'
    ];

    /**
     * Los atributos que deberían ser ocultados para la serialización.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
    ];

    

    /**
     * Obtener los atributos que deben ser convertidos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

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
        return [
            'rol' => $this->rol()->first()->nombre,
            'username' => $this->username,
        ];
    }

    // Relación con Clientes
    public function datosCliente() {
        return $this->belongsTo(DatosCliente::class, 'datos_cliente_id');
    }

    // Relación con Empleados
    public function datosEmpleado() {
        return $this->belongsTo(DatosEmpleado::class, 'datos_empleado_id');
    }

    /**
     * Relación con el rol
     */
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'id');
    }

}
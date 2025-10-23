<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Insertamos el registro en tabla datos
        $idDato = DB::table('datos')->insertGetId([
            'nombre' => 'Carlos',
            'apellidoPaterno' => 'Guevara',
            'apellidoMaterno' => 'Sosa',
            'sexo' => 'Masculino',
            'dni' => '67856473',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Insertamos el usuario con idDato relacionado
        DB::table('usuarios')->insert([
            'username' => 'adminguevara',
            'password' => Hash::make('123456'),
            'id_Datos' => $idDato,
            'id_Rol' => 1, // Rol Admin
            'estado' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $defaultPass = Hash::make('123456');

        // --- 1. SUPER ADMINISTRADOR (EMPLEADO) ---
        $idDatoSuper = DB::table('datos_empleados')->insertGetId([
            'nombre' => 'SUPER',
            'apellidoPaterno' => 'ADMINISTRADOR',
            'apellidoMaterno' => 'SISTEMA',
            'estadoCivil' => '-', 
            'sexo' => '-',      
            'dni' => '00000000',
            'fechaNacimiento' => '1990-01-01',
            'direccion' => 'OFICINA CENTRAL',
            'telefono' => '999999999',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('usuarios')->insert([
            'username' => 'superadmin',
            'password' => $defaultPass,
            'datos_empleado_id' => $idDatoSuper, // FK a empleados
            'datos_cliente_id' => null,
            'rol_id' => 1, // SuperAdmin
            'estado' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // --- 2. ADMINISTRADOR (EMPLEADO) ---
        $idDatoAdmin = DB::table('datos_empleados')->insertGetId([
            'nombre' => 'ADMINISTRADOR',
            'apellidoPaterno' => 'SEDE',
            'apellidoMaterno' => 'PRINCIPAL',
            'estadoCivil' => '-',
            'sexo' => '-',
            'dni' => '11111111',
            'fechaNacimiento' => '1990-01-01',
            'direccion' => 'SEDE SULLANA',
            'telefono' => '988888888',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('usuarios')->insert([
            'username' => 'admin',
            'password' => $defaultPass,
            'datos_empleado_id' => $idDatoAdmin, // FK a empleados
            'datos_cliente_id' => null,
            'rol_id' => 2, // Admin
            'estado' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

    }
}
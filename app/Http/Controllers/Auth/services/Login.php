<?php

namespace App\Http\Controllers\Auth\services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\services\TokenService;

class Login
{
    public function execute($username, $password, $rememberMe, $ip, $userAgent)
    {
        // 1. Buscar Usuario con sus relaciones (Eager Loading)
        // Incluimos 'rol' para que el TokenService y los Middlewares tengan la data lista
        $user = User::with(['rol', 'datosCliente', 'datosEmpleado'])
                    ->where('username', $username)
                    ->first();

        if (!$user) {
            return ['status' => 401, 'data' => ['message' => 'Usuario o contraseña incorrectos']];
        }

        // 2. Validación de Hash (Contraseña)
        if (!Hash::check($password, $user->password)) {
            return ['status' => 401, 'data' => ['message' => 'Usuario o contraseña incorrectos']];
        }

        // 3. Validar Estado del Usuario
        if ($user->estado !== 1) {
            return ['status' => 403, 'data' => ['message' => 'Error: el usuario se encuentra inactivo']];
        }

        // 4. Login Exitoso y Generación de Tokens
        try {
            // Limpiamos tokens previos para evitar sesiones basura
            DB::table('tokens')->where('usuario_id', $user->id)->delete();

            // Generamos el par de tokens (Access y Refresh)
            $tokens = TokenService::generateTokens($user, $rememberMe ?? false, $ip, $userAgent);

            return [
                'status' => 200,
                'data' => [
                    'message' => 'Login exitoso',
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 500, 'data' => ['message' => 'Error interno al generar sesión']];
        }
    }
}
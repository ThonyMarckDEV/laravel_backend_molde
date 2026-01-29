<?php

namespace App\Http\Controllers\Auth\services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Auth\services\TokenService;

class LoginCliente
{
    public function execute($username, $password, $rememberMe, $ip, $userAgent)
    {

        $user = User::with(['rol', 'datosCliente', 'datosEmpleado'])
                    ->where('username', $username)
                    ->first();

        if (!$user) {
            return ['status' => 401, 'data' => ['message' => 'Usuario o contraseña incorrectos']];
        }

        if (!Hash::check($password, $user->password)) {
            return ['status' => 401, 'data' => ['message' => 'Usuario o contraseña incorrectos']];
        }

        if ($user->estado !== 1) {
            return ['status' => 403, 'data' => ['message' => 'Error: estado del usuario inactivo']];
        }

        try {
            DB::table('tokens')->where('usuario_id', $user->id)->delete();
            Log::info('Sesiones antiguas eliminadas para idUsuario: ' . $user->id);

            // Pasamos el usuario con las relaciones ya cargadas
            $tokens = TokenService::generateTokens($user, $rememberMe ?? false, $ip, $userAgent);

            // Cookie lógica
            $minutes = $rememberMe ? (7 * 24 * 60) : (24 * 60);
            
            // name, value, minutes, path, domain, secure, httpOnly, sameSite
            $cookie = cookie(
                'refresh_token', 
                $tokens['refresh_token'], 
                $minutes, 
                '/', 
                null, 
                false, // false en local, true en prod (https)
                true,  // HttpOnly
                false, 
                'Lax'
            );

            return [
                'status' => 200,
                'data' => [
                    'message' => 'Login exitoso',
                    'access_token' => $tokens['access_token'],
                ],
                'cookie' => $cookie
            ];

        } catch (\Exception $e) {
            Log::error('Error generando tokens: ' . $e->getMessage());
            return ['status' => 500, 'data' => ['message' => 'Error interno al generar sesión']];
        }
    }
}
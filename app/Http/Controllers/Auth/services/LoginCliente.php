<?php

namespace App\Http\Controllers\Auth\services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// Importamos el helper de Cookie
use Illuminate\Support\Facades\Cookie; 
use App\Http\Controllers\Auth\services\TokenService;

class LoginCliente
{
    /**
     * Ejecuta la lógica de autenticación.
     * Retorna un array con 'status', 'data' y 'cookie' (opcional).
     */
    public function execute($username, $password, $rememberMe, $ip, $userAgent)
    {
        // 1. Buscar Usuario
        $user = User::with(['rol', 'datos'])->where('username', $username)->first();

        if (!$user) {
            return ['status' => 401, 'data' => ['message' => 'Usuario o contraseña incorrectos']];
        }

        // 2. Validación de Hash de Contraseña
        if (!Hash::check($password, $user->password)) {
            return ['status' => 401, 'data' => ['message' => 'Usuario o contraseña incorrectos']];
        }

        // 3. Validar Estado
        if ($user->estado !== 1) {
            return ['status' => 403, 'data' => ['message' => 'Error: estado del usuario inactivo']];
        }

        // 4. Login Exitoso: Limpieza y Generación de Tokens
        try {
            DB::table('tokens')->where('id_Usuario', $user->id)->delete();
            Log::info('Sesiones antiguas eliminadas para idUsuario: ' . $user->id);

            $tokens = TokenService::generateTokens($user, $rememberMe ?? false, $ip, $userAgent);

            // ============================================================
            // PREPARAMOS LA COOKIE DENTRO DEL SERVICIO
            // ============================================================
            
            // Definir duración (1 día o 7 días según rememberMe)
            $minutes = $rememberMe ? (7 * 24 * 60) : (24 * 60);

            $cookie = cookie(
                'refresh_token',       // Nombre
                $tokens['refresh_token'], // Valor (Sacado del TokenService)
                $minutes,              // Minutos
                '/',                   // Path
                null,                  // Domain
                false,                 // Secure (false para localhost, true para prod)
                true,                  // HttpOnly (JS no puede leerla)
                false,                 // Raw
                'Lax'                  // SameSite
            );

            // Retornamos la estructura lista para el controlador
            return [
                'status' => 200,
                'data' => [
                    'message' => 'Login exitoso',
                    'access_token' => $tokens['access_token'],
                ],
                'cookie' => $cookie // Pasamos el objeto cookie aparte
            ];

        } catch (\Exception $e) {
            Log::error('Error generando tokens: ' . $e->getMessage());
            return ['status' => 500, 'data' => ['message' => 'Error interno al generar sesión']];
        }
    }
}
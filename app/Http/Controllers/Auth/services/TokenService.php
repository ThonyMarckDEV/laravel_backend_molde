<?php

namespace App\Http\Controllers\Auth\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TokenService
{
    /**
     * Generate access and refresh tokens for a user (used for login and googleLogin).
     *
     * @param User $user
     * @param bool $rememberMe
     * @param string $ipAddress
     * @param string $userAgent
     * @return array
     */
    public static function generateTokens(User $user, bool $rememberMe, string $ipAddress, string $userAgent): array
    {
        $now = time();
        $accessTtl = config('jwt.ttl') * 60; // Access token TTL in seconds
        $refreshTtl = $rememberMe ? 7 * 24 * 60 * 60 : 1 * 24 * 60 * 60; // Refresh token TTL
        $secret = config('jwt.secret');

        if (!$secret) {
            Log::error('JWT_SECRET no está definido en config');
            throw new \Exception('Clave secreta JWT no configurada');
        }

        // Access token payload
        $accessPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $accessTtl,
            'nbf' => $now,
            'jti' => Str::random(16),
            'sub' => $user->id,
            'prv' => sha1(config('app.key')),
            'rol' => $user->rol->nombre,
            'username' => $user->username,
            'nombre' => $user->datos->nombre ?? 'N/A',
        ];

        // Refresh token payload
        $refreshPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $refreshTtl,
            'nbf' => $now,
            'jti' => Str::random(16),
            'sub' => $user->id,
            'prv' => sha1(config('app.key')),
            'type' => 'refresh',
            'rol' => $user->rol->nombre,
        ];

        // Generate tokens
        $accessToken = JWT::encode($accessPayload, $secret, 'HS256');
        $refreshToken = JWT::encode($refreshPayload, $secret, 'HS256');

        // Insertamos los tokens en la tabla tokens
        DB::table('tokens')->insertGetId([
            'id_Usuario' => $user->id,
            'refresh_token' => $refreshToken,
            'refresh_expires_at' => date('Y-m-d H:i:s', $now + $refreshTtl),
            'access_token' => $accessToken,
            'access_expires_at' => date('Y-m-d H:i:s', $now + $accessTtl),
            'ip_address' => $ipAddress,
            'device' => $userAgent,
            'created_at' => date('Y-m-d H:i:s', $now),
            'updated_at' => date('Y-m-d H:i:s', $now),
        ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $accessTtl,
        ];
    }

    /**
     * Generate only an access token for a user (used for refresh).
     * Y ACTUALIZA LA BASE DE DATOS.
     *
     * @param User $user
     * @param string $ipAddress
     * @param string $userAgent
     * @param string $refreshToken
     * @return string
     */
    public static function generateAccessToken(User $user, string $ipAddress, string $userAgent, string $refreshToken): string
    {
        $now = time();
        $accessTtl = config('jwt.ttl') * 60; // Access token TTL in seconds
        $secret = config('jwt.secret');

        if (!$secret) {
            Log::error('JWT_SECRET no está definido en config');
            throw new \Exception('Clave secreta JWT no configurada');
        }

        // Access token payload
        $accessPayload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $accessTtl,
            'nbf' => $now,
            'jti' => Str::random(16),
            'sub' => $user->id,
            'prv' => sha1(config('app.key')),
            'rol' => $user->rol->nombre,
            'username' => $user->username,
            'nombre' => $user->datos->nombre ?? 'N/A',
        ];

        // Generate access token
        $newAccessToken = JWT::encode($accessPayload, $secret, 'HS256');

        try {
            DB::table('tokens')
                ->where('refresh_token', $refreshToken)
                ->update([
                    'access_token' => $newAccessToken,
                    'access_expires_at' => date('Y-m-d H:i:s', $now + $accessTtl), // Actualiza la expiración
                    'ip_address' => $ipAddress,
                    'device' => $userAgent,
                    'updated_at' => date('Y-m-d H:i:s', $now)
                ]);
            
            Log::info('Access token renovado y actualizado en BD para UserID: ' . $user->id);

        } catch (\Exception $e) {
            Log::error('Error al actualizar access token en BD: ' . $e->getMessage());
            // Lanza la excepción para que el AuthController la capture si es necesario
            throw new \Exception('Error al actualizar la sesión en la base de datos'); 
        }

        return $newAccessToken; // Devuelve el token generado
    }
}
<?php

namespace App\Http\Controllers\Auth\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use Carbon\Carbon;

class TokenService
{
    /**
     * Genera tokens de acceso y refresh para un usuario.
     */
    public static function generateTokens(User $user, bool $rememberMe, string $ipAddress, string $userAgent): array
    {
        try {
            $now = Carbon::now();

            
            $accessTTL = config('jwt.ttl'); // 5 minutos
            $refreshTTL = $rememberMe ? (7 * 24 * 60) : (24 * 60); // minutos

            //==================================================================================
            //Configuracion token acceso
            $accessExp = $now->copy()->addMinutes($accessTTL)->timestamp;

            $datos = $user->datosCliente ?? $user->datosEmpleado;
            
            // Access Claims
            $accessClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'username' => $user->username,
                // Usamos el atributo dinámico que definimos en el modelo User
                'nombre'   => $datos ? $datos->nombre : 'N/A',
                'type' => 'access',
                'exp'  => $accessExp
            ];

            // Creamos el token de acceso.
            $accessToken = JWTAuth::claims($accessClaims)->fromUser($user);

            //==================================================================================
            //Configuracion token refresco

            $refreshExp = $now->copy()->addMinutes($refreshTTL)->timestamp;
            
            // Refresh Claims
            $refreshClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'type' => 'refresh',
                'exp'  => $refreshExp
            ];

            // Creamos el token de refresco.
            $refreshToken = JWTAuth::claims($refreshClaims)->fromUser($user);
            //==================================================================================

            // Guardar en Base de Datos los tokens
            DB::table('tokens')->insert([
                'usuario_id' => $user->id,
                'refresh_token' => $refreshToken,
                'refresh_expires_at' => Carbon::createFromTimestamp($refreshExp),
                'access_token' => $accessToken,
                'access_expires_at' => Carbon::createFromTimestamp($accessExp),
                'ip_address' => $ipAddress,
                'device' => $userAgent,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $accessTTL * 60,
            ];

        } catch (Exception $e) {
            Log::error('Error generando tokens: ' . $e->getMessage());
            throw new Exception("Error al generar tokens: " . $e->getMessage());
        }
    }

    /**
     * Genera solo un access token nuevo (al usar el refresh token válido).
     */
    public static function generateAccessToken(User $user, string $ipAddress, string $userAgent, string $refreshToken): string
    {
        try {
            $now = Carbon::now();
            $accessTTL = config('jwt.ttl'); // 5 minutos
            $accessExp = $now->copy()->addMinutes($accessTTL)->timestamp;

            $datos = $user->datosCliente ?? $user->datosEmpleado;

            $accessClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'username' => $user->username,
                'nombre'   => $datos ? $datos->nombre : 'N/A',
                'type' => 'access',
                'exp'  => $accessExp
            ];

            // Creamos el token de acceso.
            $newAccessToken = JWTAuth::claims($accessClaims)->fromUser($user);

            // Actualizar la base de datos
            DB::table('tokens')
                ->where('refresh_token', $refreshToken)
                ->update([
                    'access_token' => $newAccessToken,
                    'access_expires_at' => Carbon::createFromTimestamp($accessExp),
                    'ip_address' => $ipAddress,
                    'device' => $userAgent,
                    'updated_at' => $now
                ]);

            Log::info("Access token renovado en BD para usuario {$user->id}");

            return $newAccessToken;

        } catch (Exception $e) {
            Log::error('Error al generar access token con refresh: ' . $e->getMessage());
            throw new Exception("Error al renovar token: " . $e->getMessage());
        }
    }
}
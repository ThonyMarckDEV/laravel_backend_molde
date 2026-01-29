<?php

namespace App\Http\Controllers\Auth\Services;

use App\Models\User;
use App\Models\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use Carbon\Carbon;

class TokenService
{
    /**
     * Verifica si el negocio tiene datos básicos del negocio registrados.
     */
    private static function checkConfiguracion(): int
    {
        try {
            $config = Config::first();
            if ($config && !empty($config->nombre_negocio) && !empty($config->ruc)) {
                return 1;
            }
            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Genera tokens de acceso y refresh para un usuario.
     */
    public static function generateTokens(User $user, bool $rememberMe, string $ipAddress, string $userAgent): array
    {
        try {
            $now = Carbon::now('America/Lima');
            
            $accessTTL = config('jwt.ttl'); // Ej: 5 min
            $refreshTTL = $rememberMe ? (7 * 24 * 60) : (24 * 60); // 7 días o 24 horas

            //==================================================================================
            // 1. Generar Access Token (Solo en Memoria)
            //==================================================================================
            $accessExp = $now->copy()->addMinutes($accessTTL)->timestamp;
            $statusConfig = ($user->id_Rol == 1) ? self::checkConfiguracion() : null;

            $accessClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'username' => $user->username,
                'nombre' => $user->datos->nombre ?? 'N/A',
                'type' => 'access',
                'exp'  => $accessExp,
                'sede_id' => $user->sede_id,
                'nombre_sede' => $user->sede->nombre ?? 'N/A',
            ];

            if ($user->id_Rol == 1) {
                $accessClaims['configurado'] = $statusConfig;
            }

            $accessToken = JWTAuth::claims($accessClaims)->fromUser($user);

            //==================================================================================
            // 2. Generar Refresh Token (Para Base de Datos y Cookie)
            //==================================================================================
            $refreshExp = $now->copy()->addMinutes($refreshTTL)->timestamp;

            $refreshClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'type' => 'refresh',
                'exp'  => $refreshExp,
            ];

            $refreshToken = JWTAuth::claims($refreshClaims)->fromUser($user);

            //==================================================================================
            // 3. Persistencia en Base de Datos (SOLO REFRESH TOKEN)
            //==================================================================================
            $nowStr = $now->toDateTimeString();
            $refreshExpStr = Carbon::createFromTimestamp($refreshExp, 'America/Lima')->toDateTimeString();

            DB::table('tokens')->insert([
                'id_Usuario' => $user->id,
                'refresh_token' => $refreshToken,
                'refresh_expires_at' => $refreshExpStr,
                'ip_address' => $ipAddress,
                'device' => $userAgent,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
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
            $accessTTL = config('jwt.ttl'); 
            $now = Carbon::now('America/Lima');
            $accessExp = $now->copy()->addMinutes($accessTTL)->timestamp;

            $statusConfig = self::checkConfiguracion();

            $accessClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'username' => $user->username,
                'nombre' => $user->datos->nombre ?? 'N/A',
                'configurado' => $statusConfig,
                'type' => 'access',
                'exp'  => $accessExp
            ];

            // Generamos el JWT Access Token (Solo se devuelve, no se guarda)
            $newAccessToken = JWTAuth::claims($accessClaims)->fromUser($user);
            
            $nowStr = $now->toDateTimeString();

            // Actualizamos la info de auditoría
            DB::table('tokens')
                ->where('refresh_token', $refreshToken)
                ->update([
                    'ip_address' => $ipAddress,
                    'device' => $userAgent,
                    'updated_at' => $nowStr
                ]);

            Log::info("Access token renovado con status configurado: {$statusConfig}");

            return $newAccessToken;

        } catch (Exception $e) {
            Log::error('Error al generar access token con refresh: ' . $e->getMessage());
            throw new Exception("Error al renovar token: " . $e->getMessage());
        }
    }
}
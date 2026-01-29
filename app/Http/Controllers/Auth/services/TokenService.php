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
     * Helper privado para determinar el nombre según si es Empleado o Cliente.
     * Lógica: Si tiene ID de empleado, buscamos en la relación datosEmpleado.
     * Si no, si tiene ID de cliente, buscamos en datosCliente.
     */
    private static function obtenerNombreSeguro(User $user): string
    {
        // Prioridad: Empleado
        if (!empty($user->datos_empleado_id)) {
            // Asegurarse de que la relación esté cargada o accederla
            return $user->datosEmpleado->nombre ?? 'Sin Nombre Empleado';
        }

        // Caso: Cliente
        if (!empty($user->datos_cliente_id)) {
            return $user->datosCliente->nombre ?? 'Sin Nombre Cliente';
        }

        return 'Usuario Sin Datos';
    }

    /**
     * Genera tokens de acceso y refresh para un usuario.
     */
    public static function generateTokens(User $user, bool $rememberMe, string $ipAddress, string $userAgent): array
    {
        try {
            $now = Carbon::now('America/Lima');
            
            $accessTTL = config('jwt.ttl'); 
            $refreshTTL = $rememberMe ? (7 * 24 * 60) : (24 * 60); 

            // 1. Obtener Nombre usando la lógica explicita
            $nombreUser = self::obtenerNombreSeguro($user);

            // ==================================================================================
            // ACCESS TOKEN
            // ==================================================================================
            $accessExp = $now->copy()->addMinutes($accessTTL)->timestamp;

            $accessClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'username' => $user->username,
                'nombre' => $nombreUser,
                'type' => 'access',
                'exp'  => $accessExp
            ];

            $accessToken = JWTAuth::claims($accessClaims)->fromUser($user);

            // ==================================================================================
            // REFRESH TOKEN
            // ==================================================================================
            $refreshExp = $now->copy()->addMinutes($refreshTTL)->timestamp;

            $refreshClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'type' => 'refresh',
                'exp'  => $refreshExp
            ];

            $refreshToken = JWTAuth::claims($refreshClaims)->fromUser($user);

            // ==================================================================================
            // PERSISTENCIA (SOLO REFRESH TOKEN)
            // ==================================================================================
            $nowStr = $now->toDateTimeString();
            $refreshExpStr = Carbon::createFromTimestamp($refreshExp, 'America/Lima')->toDateTimeString();

            DB::table('tokens')->insert([
                'usuario_id' => $user->id,
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
     * Genera solo un access token nuevo.
     */
    public static function generateAccessToken(User $user, string $ipAddress, string $userAgent, string $refreshToken): string
    {
        try {
            $accessTTL = config('jwt.ttl'); 
            $now = Carbon::now('America/Lima');
            $accessExp = $now->copy()->addMinutes($accessTTL)->timestamp;

            // 1. Obtener Nombre usando la lógica explicita
            $nombreUser = self::obtenerNombreSeguro($user);

            $accessClaims = [
                'sub' => $user->id,
                'rol' => $user->rol->nombre,
                'username' => $user->username,
                'nombre' => $nombreUser,
                'type' => 'access',
                'exp'  => $accessExp
            ];

            $newAccessToken = JWTAuth::claims($accessClaims)->fromUser($user);
            
            $nowStr = $now->toDateTimeString();

            // Actualizamos auditoría del refresh token existente
            DB::table('tokens')
                ->where('refresh_token', $refreshToken)
                ->update([
                    'ip_address' => $ipAddress,
                    'device' => $userAgent,
                    'updated_at' => $nowStr
                ]);

            Log::info("Access token renovado para: {$nombreUser}");

            return $newAccessToken;

        } catch (Exception $e) {
            Log::error('Error al generar access token con refresh: ' . $e->getMessage());
            throw new Exception("Error al renovar token: " . $e->getMessage());
        }
    }
}
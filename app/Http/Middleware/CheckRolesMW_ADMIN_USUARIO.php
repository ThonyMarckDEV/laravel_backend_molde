<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckRolesMW_ADMIN_USUARIO
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles (Permite recibir múltiples roles: 'admin', 'empleado', etc.)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // 1. Definir roles por defecto si no se pasan en la ruta
        if (empty($roles)) {
            $roles = ['usuario', 'admin'];
        }

        // 2. Verificar autenticación (Valida la firma del JWT)
        if (!Auth::check()) {
            return response()->json(['message' => 'No autorizado (No autenticado)'], 401);
        }

        // 3. OBTENER EL USUARIO Y SU ROL DESDE LA BASE DE DATOS
        // No usamos $payload->get('rol') para evitar manipulaciones del cliente.
        $user = Auth::user();
        $userRoleDB = $user->rol ? $user->rol->nombre : null;

        // 4. Verificar si el usuario tiene un rol asignado en el sistema
        if (!$userRoleDB) {
            return response()->json([
                'message' => 'No autorizado (Usuario sin rol asignado en BD)'
            ], 403);
        }

        // 5. Verificar si el rol de la BD está en la lista de permitidos
        if (!in_array($userRoleDB, $roles)) {
            
            // Logueamos el intento de acceso fallido para auditoría
            Log::warning("Acceso denegado: Usuario {$user->username} intentó entrar a ruta de [" . implode(', ', $roles) . "] con el rol: {$userRoleDB}");

            return response()->json([
                'status' => 'error',
                'message' => 'Acceso denegado. Se requiere uno de estos roles: ' . implode(', ', $roles),
                'tu_rol_actual' => $userRoleDB
            ], 403);
        }

        return $next($request);
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckRoleMW
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string[]  ...$roles  <-- Captura múltiples parámetros como un array
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // Verificar autenticación
        if (!Auth::check()) {
            return response()->json([
                'message' => 'No autorizado (Sesión inválida o expirada)'
            ], 401);
        }

        $user = Auth::user();
        $userRole = $user->rol->nombre;

        // Verificación de Seguridad:
        // Comprobamos si el rol del usuario está dentro del array de roles permitidos
        if (!$user || !in_array($userRole, $roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Acceso denegado. Requiere uno de los siguientes roles: ' . implode(', ', $roles),
                'tu_rol_actual' => $userRole ?? 'Ninguno'
            ], 403);
        }

        return $next($request);
    }
}
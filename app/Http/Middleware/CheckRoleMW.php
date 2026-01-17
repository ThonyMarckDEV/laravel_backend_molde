<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CheckRoleMW
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role  (El rol requerido enviado desde el archivo de rutas)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        // 1. Verificar si el usuario está autenticado por JWT
        // Esto ya valida que la FIRMA del token sea correcta.
        if (!Auth::check()) {
            return response()->json([
                'message' => 'No autorizado (Sesión inválida o expirada)'
            ], 401);
        }

        // 2. Obtener el usuario autenticado
        $user = Auth::user();

        Log::info("Rol de la BD: " . $user->rol->nombre);

        // 3. Verificación de Seguridad Máxima: 
        // Consultamos el rol REAL en la base de datos a través de la relación.
        // Esto evita que un usuario use un token antiguo con un rol que ya no tiene.
        if (!$user || $user->rol->nombre !== $role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Acceso denegado. Se requiere el rol: ' . $role,
                'tu_rol_actual' => $user ? $user->rol->nombre : 'Ninguno'
            ], 403);
        }

        return $next($request);
    }
}
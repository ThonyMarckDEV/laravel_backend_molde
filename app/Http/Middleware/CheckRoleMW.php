<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; 

class CheckRoleMW
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role  
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $role)
    {
        // 1. Verificar si el usuario está autenticado
        if (!Auth::check()) {
            return response()->json(['message' => 'No autorizado (No autenticado)'], 401);
        }

        // 2. Obtener el payload  del token JWT
        $payload = auth()->payload();

        // 3. Obtener el rol desde el payload
        $userRole = $payload->get('rol');

        // 4. Verificar si el claim 'rol' existe en el token
        if (!$userRole) {
            return response()->json([
                'message' => 'No autorizado (El token no contiene un rol)'
            ], 403);
        }

        // 5. Verificar que el rol coincida
        if ($userRole !== $role) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere rol: ' . $role,
                'tu_rol_es' => $userRole
            ], 403);
        }

        return $next($request);
    }
}
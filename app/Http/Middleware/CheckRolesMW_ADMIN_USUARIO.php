<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRolesMW_ADMIN_USUARIO
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {

        if (empty($roles)) {
            $roles = ['admin', 'usuario']; // Roles por defecto para este middleware
        }

        // 2. Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return response()->json(['message' => 'No autorizado (No autenticado)'], 401);
        }

        // 3. Obtener el payload  del token JWT
        $payload = auth()->payload();
        
        // 4. Obtener el rol que viene DENTRO del token
        $userRole = $payload->get('rol');

        // 5. Verificar si el claim 'rol' existe en el token
        if (!$userRole) {
            return response()->json([
                'message' => 'No autorizado (El token no contiene un rol)'
            ], 403);
        }

        // 6. Verificar si el rol del usuario está en la lista de roles permitidos
        if (!in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere uno de estos roles: ' . implode(', ', $roles),
                'tu_rol_es' => $userRole 
            ], 403);
        }


        return $next($request);
    }
}
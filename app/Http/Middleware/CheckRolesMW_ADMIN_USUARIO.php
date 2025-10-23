<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

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
            $roles = ['admin' ,'usuario'];
        }

        // Verificar que el usuario está autenticado y el payload está disponible
        if (!$request->auth || !isset($request->auth->rol)) {
            return response()->json([
                'message' => 'No autorizado'
            ], 403);
        }

        // Verificar si el rol del usuario está en la lista de roles permitidos
        if (!in_array($request->auth->rol, $roles)) {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere uno de estos roles: ' . implode(', ', $roles)
            ], 403);
        }

        return $next($request);
    }
}
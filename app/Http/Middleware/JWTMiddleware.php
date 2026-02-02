<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class JWTMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        
            // Obtenemos el ID único (JTI) del token que viene en la petición
            $jti = JWTAuth::getPayload()->get('jti');

            // Verificamos si este JTI específico es el que está activo en la BD
            $session = DB::table('tokens')
                ->where('usuario_id', $user->id)
                ->where('access_token_id', $jti)
                ->first();

            if (!$session) {
                // Si el JTI no coincide, es un token de una sesión vieja/revocada
                return response()->json(['message' => 'Sesión terminada en otro dispositivo'], 401);
            }

        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'El token ha expirado'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['message' => 'Token inválido'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token no proporcionado'], 401);
        }

        return $next($request);
    }
}
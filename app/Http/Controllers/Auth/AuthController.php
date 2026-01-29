<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\services\LoginCliente;
use App\Http\Controllers\Auth\services\TokenService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
     // ==========================================
    // LOGIN
    // ==========================================
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'remember_me' => 'boolean',
        ]);

        try {
            $loginService = new LoginCliente();
            
            // El servicio ahora se encarga de todo (tokens + cookie generation)
            $response = $loginService->execute(
                $request->username,
                $request->password,
                $request->remember_me,
                $request->ip(),
                $request->userAgent()
            );

            // Si hay error (no es 200), devolvemos el JSON de error
            if ($response['status'] !== 200) {
                return response()->json($response['data'], $response['status']);
            }

            // Si es éxito, el servicio nos dio 'data' (access token) y 'cookie' (refresh token)
            return response()
                ->json($response['data'], 200)
                ->withCookie($response['cookie']);

        } catch (\Exception $e) {
            Log::error('Error en AuthController@login: ' . $e->getMessage());
            return response()->json(['message' => 'Error al iniciar sesión'], 500);
        }
    }


    // ==========================================
    // REFRESH TOKEN (Ruta /api/refresh)
    // ==========================================
    public function refresh(Request $request)
    {
        // Obtener el token de la COOKIE
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'No se proporcionó refresh token (Cookie vacía)'], 401);
        }

        try {
            // Buscar en BD
            $storedToken = DB::table('tokens')->where('refresh_token', $refreshToken)->first();

            if (!$storedToken) {
                // Si no existe, borramos la cookie por si acaso
                return response()->json(['message' => 'Sesión inválida o revocada'], 401)
                    ->withCookie(Cookie::forget('refresh_token'));
            }

            // Validar Expiración
            if (isset($storedToken->refresh_expires_at) && now()->greaterThan($storedToken->refresh_expires_at)) {
                DB::table('tokens')->where('id', $storedToken->id)->delete();
                return response()->json(['message' => 'Sesión expirada'], 401)
                    ->withCookie(Cookie::forget('refresh_token'));
            }

            // Generar NUEVO Access Token
            $user = User::find($storedToken->usuario_id);
            if (!$user) {
                return response()->json(['message' => 'Usuario no encontrado'], 401);
            }

            $newAccessToken = TokenService::generateAccessToken(
                $user, 
                $request->ip(), 
                $request->userAgent(), 
                $refreshToken
            );

            // Retornar solo el Access Token (La cookie refresh se mantiene igual)
            return response()->json([
                'access_token' => $newAccessToken,
                'message' => 'Token renovado'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error en refresh: ' . $e->getMessage());
            // Si hay error grave, limpiamos cookie
            return response()->json(['message' => 'Error al renovar sesión'], 401)
                ->withCookie(Cookie::forget('refresh_token'));
        }
    }

    // ==========================================
    // LOGOUT
    // ==========================================
    public function logout(Request $request)
    {
        // Obtener token de la cookie
        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            // Eliminar de la BD
            DB::table('tokens')->where('refresh_token', $refreshToken)->delete();
        }

        // Crear cookie de borrado (expira en el pasado)
        $cookie = Cookie::forget('refresh_token');

        return response()->json(['message' => 'Sesión cerrada correctamente'], 200)
            ->withCookie($cookie);
    }

    // ==========================================
    // ME (Perfil)
    // ==========================================
    public function me()
    {
        $user = auth()->user(); // Esto lo llena el Middleware JWT

        if (!$user) {
            return response()->json(['message' => 'No autorizado'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'rol' => [
                'nombre' => $user->rol->nombre
            ]
        ]);
    }
}
<?php

namespace App\Http\Controllers\Auth;

//SERVICIOS
use App\Http\Controllers\Auth\services\TokenService;
use App\Http\Controllers\Auth\utilities\AuthValidations;
use App\Http\Controllers\Auth\utilities\LoginSecurityUtility;
use App\Http\Controllers\Controller;
use App\Mail\PasswordResetEmail;
use App\Models\User;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    /**
     * Handle username/password login.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validate request
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'remember_me' => 'boolean',
        ]);

        try {

            $user = User::with('rol')->where('username', $request->username)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Usuario o contraseña incorrectos',
                ], 401);
            }

            if ($user->estado !== 1) {
                return response()->json([
                    'message' => 'Error: estado del usuario inactivo',
                ], 403);
            }
            
            DB::table('refresh_tokens')
                ->where('id_Usuario', $user->id)
                ->delete();
            Log::info('Sesiones antiguas eliminadas para idUsuario: ' . $user->id);


            $tokens = TokenService::generateTokens($user, $request->remember_me ?? false, $request->ip(), $request->userAgent());

            return response()->json([
                'message' => 'Login exitoso',
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en login: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al iniciar sesión',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh access token using refresh token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        // Validate request
        $validator = AuthValidations::validateRefreshToken($request); // <- Usa la validación estándar
        if ($validator->fails()) {
            Log::warning('Validación de refresh token fallida: ' . json_encode($validator->errors()));
            return response()->json([
                'message' => 'Refresh token inválido',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {

            $secret = config('jwt.secret');
            if (!$secret) {
                Log::error('JWT_SECRET no está definido en config');
                throw new \Exception('Clave secreta JWT no configurada');
            }
            Log::info('Intentando decodificar refresh token con secret: ' . substr($secret, 0, 10) . '...');
            $payload = JWT::decode($request->refresh_token, new Key($secret, 'HS256'));
            Log::info('Payload decodificado: ' . json_encode($payload));


            if (!isset($payload->type) || $payload->type !== 'refresh') {
                Log::warning('Token no es de tipo refresh: ' . json_encode($payload));
                return response()->json([
                    'message' => 'El token proporcionado no es un token de refresco',
                ], 401);
            }


            $user = User::with('rol')->find($payload->sub);
            if (!$user) {
                Log::error('Usuario no encontrado para sub: ' . $payload->sub);
                return response()->json([
                    'message' => 'Usuario no encontrado',
                ], 404);
            }


            $storedToken = DB::table('refresh_tokens')
                ->where('id_Usuario', $user->id)
                ->where('token', $request->refresh_token) 
                ->first();

            if (!$storedToken) {
                Log::warning('Refresh token no encontrado en la BD (posiblemente revocado) para usuario: ' . $user->id);
                return response()->json([
                    'message' => 'Token no válido o no autorizado',
                ], 401);
            }

            // Generate new access token only
            $accessToken = TokenService::generateAccessToken($user, $request->ip(), $request->userAgent());
            Log::info('Nuevo access token generado para usuario: ' . $user->id);

            return response()->json([
                'message' => 'Token actualizado',
                'access_token' => $accessToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ], 200);
        } catch (\Firebase\JWT\ExpiredException $e) {
            Log::error('Refresh token expirado: ' . $e->getMessage());
            // Si expira, también lo borramos de la BD
            DB::table('refresh_tokens')->where('token', $request->refresh_token)->delete();
            return response()->json([
                'message' => 'Refresh token expirado',
            ], 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            Log::error('Firma de refresh token inválida: ' . $e->getMessage());
            return response()->json([
                'message' => 'Refresh token inválido',
            ], 401);
        } catch (\Exception $e) {
            Log::error('Error al procesar el token: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al procesar el token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate refresh token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateRefreshToken(Request $request)
    {

        $validator = AuthValidations::validateRefreshToken($request);
        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {

            $refreshToken = DB::table('refresh_tokens')
                ->where('token', $request->refresh_token)
                ->first();

            if (!$refreshToken) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Token no válido o no autorizado',
                ], 401);
            }


            if ($refreshToken->expires_at && now()->greaterThan($refreshToken->expires_at)) {
                DB::table('refresh_tokens')
                    ->where('token', $request->refresh_token)
                    ->delete();

                return response()->json([
                    'valid' => false,
                    'message' => 'Token expirado',
                ], 401);
            }

            return response()->json([
                'valid' => true,
                'message' => 'Token válido',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error validating refresh token: ' . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Error al validar el token',
            ], 500);
        }
    }

    /**
     * Handle user logout.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {


        $validator = AuthValidations::validateLogout($request);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        $deleted = DB::table('refresh_tokens')
            ->where('token', $request->refresh_token)
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'OK',
            ], 200);
        }

        return response()->json([
            'message' => 'Error: No se encontró el token de refresco',
        ], 404);
    }
}
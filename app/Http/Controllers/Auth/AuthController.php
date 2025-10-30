<?php

namespace App\Http\Controllers\Auth;

//SERVICIOS
use App\Http\Controllers\Auth\services\TokenService; // Asegúrate que el namespace sea correcto
use App\Http\Controllers\Auth\utilities\AuthValidations;
use App\Http\Controllers\Controller;
use App\Models\User;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

// Importaciones añadidas para la nueva lógica de validación
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;


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
            
            DB::table('tokens')
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
     * Validate access and refresh tokens.
     * Si el access token está vencido pero el refresh es válido, lo renueva.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateTokens(Request $request)
    {
        // 1. Validar que vengan ambos tokens
        $validator = AuthValidations::validateTokenPair($request);
        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            // 2. Buscar el refresh token en la BD
            $storedToken = DB::table('tokens')
                ->where('refresh_token', $request->refresh_token)
                ->first();

            // 3. Validar si el Refresh Token existe en la BD
            if (!$storedToken) {
                Log::warning('validateTokens: Refresh token no encontrado en BD.');
                return response()->json([
                    'valid' => false,
                    'message' => 'Sesión no válida o revocada (refresh token no encontrado)',
                ], 401);
            }

            // 3b. Validar la expiración del REFRESH token usando la columna
            if (isset($storedToken->refresh_expires_at) && now()->greaterThan($storedToken->refresh_expires_at)) {
                Log::warning('validateTokens: Refresh token expirado en BD.');
                
                DB::table('tokens')->where('refresh_token', $request->refresh_token)->delete();
                return response()->json([
                    'valid' => false,
                    'message' => 'Sesión expirada (refresh token vencido)',
                ], 401);
            }


            // 4. Validar Access Token
            if (!isset($storedToken->access_token) || $storedToken->access_token !== $request->access_token) {
                Log::warning('validateTokens: Access token no coincide con el de la BD. UserID: ' . $storedToken->id_Usuario);
                return response()->json([
                    'valid' => false,
                    'message' => 'Discrepancia de tokens. Sesión inválida.',
                ], 401);
            }

            // 5. Verificar expiración de Access Token (JWT)
            $secret = config('jwt.secret');
            if (!$secret) {
                Log::error('validateTokens: JWT_SECRET no está definido');
                throw new \Exception('Clave secreta JWT no configurada');
            }

            try {
                // Intentamos decodificar el ACCESS token para chequear su 'exp' claim
                JWT::decode($request->access_token, new Key($secret, 'HS256'));

                // Tokens 100% válidos y vigentes
                return response()->json([
                    'valid' => true,
                    'message' => 'OK, tokens validos',
                ], 200);

            } catch (ExpiredException $e) {
                // El Access Token coincidía pero está expirado -> Renovar
                Log::info('validateTokens: Access token expirado, renovando... UserID: ' . $storedToken->id_Usuario);

                $user = User::find($storedToken->id_Usuario);
                if (!$user) {
                    Log::error('validateTokens: Usuario no encontrado para id_Usuario: ' . $storedToken->id_Usuario);
                    return response()->json(['valid' => false, 'message' => 'Usuario asociado no encontrado'], 404);
                }
                
                // 1. Llamamos al servicio (ahora con 4 argumentos)
                $newAccessToken = TokenService::generateAccessToken(
                    $user, 
                    $request->ip(), 
                    $request->userAgent(), 
                    $request->refresh_token
                );
                
                Log::info('validateTokens: Nuevo access token generado por TokenService. UserID: ' . $storedToken->id_Usuario);


                return response()->json([
                    'valid' => true, 
                    'message' => 'Access token renovado',
                    'access_token' => $newAccessToken, 
                ], 200);
                
            } catch (SignatureInvalidException $e) {
                Log::warning('validateTokens: Firma de Access token inválida. ' . $e->getMessage());
                return response()->json(['valid' => false, 'message' => 'Access token inválido (firma)'], 401);
            } catch (\Exception $e) {
                Log::warning('validateTokens: Error al decodificar access token. ' . $e->getMessage());
                return response()->json(['valid' => false, 'message' => 'Access token no procesable'], 401);
            }

        } catch (\Exception $e) {
            Log::error('Error en validateTokens: ' . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Error al validar los tokens',
                'error' => $e->getMessage(),
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
        // ... (Tu código de logout sigue igual)
        $validator = AuthValidations::validateLogout($request);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        $deleted = DB::table('tokens')
            ->where('refresh_token', $request->refresh_token)
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
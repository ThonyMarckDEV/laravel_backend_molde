<?php

namespace App\Http\Controllers\Auth;

//SERVICIOS
use App\Http\Controllers\Auth\services\TokenService;
use App\Http\Controllers\Auth\utilities\AuthValidations;
use App\Http\Controllers\Controller;
use App\Models\User;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Auth;
use OpenApi\Annotations as OA;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Info(
 * version="1.0.0",
 * title="API de Autenticación",
 * description="Maneja el login, validación de tokens y logout."
 * )
 * @OA\Server(
 * url=L5_SWAGGER_CONST_HOST,
 * description="API Server"
 * )
 * @OA\Tag(
 * name="Autenticación",
 * description="Endpoints de autenticación de usuarios"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/login",
     * tags={"Autenticación"},
     * summary="Iniciar sesión de usuario",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"username","password"},
     * @OA\Property(property="username", type="string", example="usuario.test"),
     * @OA\Property(property="password", type="string", format="password", example="password123"),
     * @OA\Property(property="remember_me", type="boolean", example=false)
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Login exitoso",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Login exitoso"),
     * @OA\Property(property="access_token", type="string"),
     * @OA\Property(property="refresh_token", type="string")
     * )
     * ),
     * @OA\Response(response=401, description="Usuario o contraseña incorrectos"),
     * @OA\Response(response=403, description="Error: estado del usuario inactivo"),
     * @OA\Response(response=422, description="Error de validación (campos faltantes)")
     * )
     */
    public function login(Request $request)
    {
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
     * @OA\Post(
     * path="/api/validate-tokens",
     * tags={"Autenticación"},
     * summary="Validar tokens y renovar si es necesario",
     * description="Verifica la validez del par de tokens. Si el access token está expirado pero el refresh es válido, genera un nuevo access token.",
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"access_token","refresh_token"},
     * @OA\Property(property="access_token", type="string"),
     * @OA\Property(property="refresh_token", type="string")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Tokens válidos o renovados",
     * @OA\JsonContent(
     * @OA\Property(property="valid", type="boolean", example=true),
     * @OA\Property(property="message", type="string", example="OK, tokens validos / Access token renovado"),
     * @OA\Property(property="access_token", type="string", description="Opcional: Se envía solo si el token fue renovado")
     * )
     * ),
     * @OA\Response(response=400, description="Datos inválidos (faltan tokens)"),
     * @OA\Response(response=401, description="Sesión no válida, expirada o discrepancia de tokens")
     * )
     */
    public function validateTokens(Request $request)
    {
        $validator = AuthValidations::validateTokenPair($request);
        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $storedToken = DB::table('tokens')
                ->where('refresh_token', $request->refresh_token)
                ->first();

            if (!$storedToken) {
                Log::warning('validateTokens: Refresh token no encontrado en BD.');
                return response()->json([
                    'valid' => false,
                    'message' => 'Sesión no válida o revocada (refresh token no encontrado)',
                ], 401);
            }

            if (isset($storedToken->refresh_expires_at) && now()->greaterThan($storedToken->refresh_expires_at)) {
                Log::warning('validateTokens: Refresh token expirado en BD.');
                
                DB::table('tokens')->where('refresh_token', $request->refresh_token)->delete();
                return response()->json([
                    'valid' => false,
                    'message' => 'Sesión expirada (refresh token vencido)',
                ], 401);
            }


            if (!isset($storedToken->access_token) || $storedToken->access_token !== $request->access_token) {
                Log::warning('validateTokens: Access token no coincide con el de la BD. UserID: ' . $storedToken->id_Usuario);
                return response()->json([
                    'valid' => false,
                    'message' => 'Discrepancia de tokens. Sesión inválida.',
                ], 401);
            }

            $secret = config('jwt.secret');
            if (!$secret) {
                Log::error('validateTokens: JWT_SECRET no está definido');
                throw new \Exception('Clave secreta JWT no configurada');
            }

            try {
                JWT::decode($request->access_token, new Key($secret, 'HS256'));

                return response()->json([
                    'valid' => true,
                    'message' => 'OK, tokens validos',
                ], 200);

            } catch (ExpiredException $e) {
                Log::info('validateTokens: Access token expirado, renovando... UserID: ' . $storedToken->id_Usuario);

                $user = User::find($storedToken->id_Usuario);
                if (!$user) {
                    Log::error('validateTokens: Usuario no encontrado para id_Usuario: ' . $storedToken->id_Usuario);
                    return response()->json(['valid' => false, 'message' => 'Usuario asociado no encontrado'], 404);
                }
                
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


    public function logout(Request $request)
    {
        try {
            $userId = Auth::id();
            
            $tokens = DB::table('tokens')
                ->where('id_Usuario', $userId)
                ->select('access_token', 'refresh_token')
                ->first();

            if ($tokens) {
                $access_token = $tokens->access_token;
                $refresh_token = $tokens->refresh_token;

                // Revocar tokens válidos
                try {
                    if ($access_token) {
                        JWTAuth::setToken($access_token)->invalidate(true);
                    }
                } catch (\Exception $e) {
                    // Ignorar si ya estaba invalidado
                }

                try {
                    if ($refresh_token) {
                        JWTAuth::setToken($refresh_token)->invalidate(true);
                    }
                } catch (\Exception $e) {
                    // Ignorar si ya estaba invalidado
                }

                DB::table('tokens')
                    ->where('id_Usuario', $userId)
                    ->delete();
            }

            return response()->json([
                'message' => 'Sesión cerrada, tokens revocados y registro eliminado correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al cerrar sesión',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
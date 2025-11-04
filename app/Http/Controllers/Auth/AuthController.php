<?php

namespace App\Http\Controllers\Auth;

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
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/login",
     *     summary="Iniciar sesión de usuario",
     *     description="Autentica un usuario y devuelve access_token y refresh_token",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"username","password"},
     *             @OA\Property(property="username", type="string", example="admin"),
     *             @OA\Property(property="password", type="string", example="123456"),
     *             @OA\Property(property="remember_me", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login exitoso"),
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
     *             @OA\Property(property="refresh_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales incorrectas",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Usuario o contraseña incorrectos")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Usuario inactivo",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error: estado del usuario inactivo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor"
     *     )
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
                return response()->json(['message' => 'Usuario o contraseña incorrectos'], 401);
            }

            if ($user->estado !== 1) {
                return response()->json(['message' => 'Error: estado del usuario inactivo'], 403);
            }

            DB::table('tokens')->where('id_Usuario', $user->id)->delete();
            Log::info('Sesiones antiguas eliminadas para idUsuario: ' . $user->id);

            $tokens = TokenService::generateTokens($user, $request->remember_me ?? false, $request->ip(), $request->userAgent());

            return response()->json([
                'message' => 'Login exitoso',
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error en login: ' . $e->getMessage());
            return response()->json(['message' => 'Error al iniciar sesión', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/validate-tokens",
     *     summary="Validar tokens JWT",
     *     description="Valida el access_token y refresh_token. Si el access_token expiró, genera uno nuevo.",
     *     tags={"Autenticación"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"access_token","refresh_token"},
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
     *             @OA\Property(property="refresh_token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tokens válidos o renovados",
     *         @OA\JsonContent(
     *             @OA\Property(property="valid", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Access token renovado"),
     *             @OA\Property(property="access_token", type="string", example="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Tokens inválidos o expirados",
     *         @OA\JsonContent(
     *             @OA\Property(property="valid", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sesión expirada (refresh token vencido)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Datos inválidos"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al validar los tokens"
     *     )
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
            $storedToken = DB::table('tokens')->where('refresh_token', $request->refresh_token)->first();
            if (!$storedToken) {
                return response()->json(['valid' => false, 'message' => 'Sesión no válida o revocada'], 401);
            }

            if (isset($storedToken->refresh_expires_at) && now()->greaterThan($storedToken->refresh_expires_at)) {
                DB::table('tokens')->where('refresh_token', $request->refresh_token)->delete();
                return response()->json(['valid' => false, 'message' => 'Sesión expirada'], 401);
            }

            if (!isset($storedToken->access_token) || $storedToken->access_token !== $request->access_token) {
                return response()->json(['valid' => false, 'message' => 'Discrepancia de tokens'], 401);
            }

            $secret = config('jwt.secret');
            JWT::decode($request->access_token, new Key($secret, 'HS256'));

            return response()->json(['valid' => true, 'message' => 'OK, tokens válidos'], 200);
        } catch (ExpiredException $e) {
            $user = User::find($storedToken->id_Usuario);
            $newAccessToken = TokenService::generateAccessToken($user, $request->ip(), $request->userAgent(), $request->refresh_token);
            return response()->json(['valid' => true, 'message' => 'Access token renovado', 'access_token' => $newAccessToken], 200);
        } catch (\Exception $e) {
            return response()->json(['valid' => false, 'message' => 'Error al validar tokens', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     summary="Cerrar sesión",
     *     description="Revoca el access_token y refresh_token del usuario autenticado",
     *     tags={"Autenticación"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sesión cerrada, tokens revocados y registro eliminado correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al cerrar sesión"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        try {
            $userId = Auth::id();
            $tokens = DB::table('tokens')->where('id_Usuario', $userId)->first();

            if ($tokens) {
                try {
                    JWTAuth::setToken($tokens->access_token)->invalidate(true);
                    JWTAuth::setToken($tokens->refresh_token)->invalidate(true);
                } catch (\Exception $e) {}

                DB::table('tokens')->where('id_Usuario', $userId)->delete();
            }

            return response()->json(['message' => 'Sesión cerrada, tokens revocados y registro eliminado correctamente'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al cerrar sesión', 'error' => $e->getMessage()], 500);
        }
    }
}

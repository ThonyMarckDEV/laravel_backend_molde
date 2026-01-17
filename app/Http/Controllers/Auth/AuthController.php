<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\services\Login;
use App\Http\Controllers\Auth\services\Logout;
use App\Http\Controllers\Auth\utilities\AuthValidations;
use App\Http\Controllers\Controller;
use App\Models\User;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Auth\services\TokenService;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'remember_me' => 'boolean',
        ]);

        try {
            $loginService = new Login();
            
            // Delegamos toda la lógica al servicio
            $response = $loginService->execute(
                $request->username,
                $request->password,
                $request->remember_me,
                $request->ip(),
                $request->userAgent()
            );

            return response()->json($response['data'], $response['status']);

        } catch (\Exception $e) {
            Log::error('Error crítico en AuthController@login: ' . $e->getMessage());
            return response()->json(['message' => 'Error al iniciar sesión', 'error' => $e->getMessage()], 500);
        }
    }

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
            $user = User::find($storedToken->usuario_id);
            $newAccessToken = TokenService::generateAccessToken($user, $request->ip(), $request->userAgent(), $request->refresh_token);
            return response()->json(['valid' => true, 'message' => 'Access token renovado', 'access_token' => $newAccessToken], 200);
        } catch (\Exception $e) {
            return response()->json(['valid' => false, 'message' => 'Error al validar tokens', 'error' => $e->getMessage()], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            $userId = Auth::id();
            
            $logoutService = new Logout();
            $logoutService->execute($userId);

            return response()->json(['message' => 'Sesión cerrada, tokens revocados y registro eliminado correctamente'], 200);

        } catch (\Exception $e) {
            Log::error('Error en logout: ' . $e->getMessage());
            return response()->json(['message' => 'Error al cerrar sesión', 'error' => $e->getMessage()], 500);
        }
    }
}

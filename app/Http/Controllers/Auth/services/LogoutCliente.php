<?php

namespace App\Http\Controllers\Auth\services;

use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class LogoutCliente
{
    /**
     * Ejecuta la lógica de cierre de sesión.
     */
    public function execute($userId)
    {
        $tokens = DB::table('tokens')->where('usuario_id', $userId)->first();

        if ($tokens) {
            // Intentar invalidar los tokens en JWTAuth (Blacklist)
            try {
                if (!empty($tokens->access_token)) {
                    JWTAuth::setToken($tokens->access_token)->invalidate(true);
                }
                if (!empty($tokens->refresh_token)) {
                    JWTAuth::setToken($tokens->refresh_token)->invalidate(true);
                }
            } catch (\Exception $e) {
                // Si el token ya expiró o es inválido, continuamos para borrarlo de la BD
            }

            // Eliminar registro de la base de datos
            DB::table('tokens')->where('usuario_id', $userId)->delete();
        }

        return true;
    }
}
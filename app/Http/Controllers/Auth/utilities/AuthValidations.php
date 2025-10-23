<?php

namespace App\Http\Controllers\Auth\utilities;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class AuthValidations
{
    /**
     * Validate login request data.
     *
     * @param Request $request
     * @return \Illuminate\Validation\Validator
     */
    public static function validateLogin(Request $request)
    {
        return Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
            'remember_me' => 'boolean',
        ], [
            'username.required' => 'El nombre de usuario es obligatorio.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);
    }

    /**
     * Validate refresh token request data.
     *
     * @param Request $request
     * @return \Illuminate\Validation\Validator
     */
    public static function validateRefreshToken(Request $request)
    {
        return Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ], [
            'refresh_token.required' => 'El token de refresco es obligatorio.',
        ]);
    }

    /**
     * Validate logout request data.
     *
     * @param Request $request
     * @return \Illuminate\Validation\Validator
     */
    public static function validateLogout(Request $request)
    {
       
        return Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ], [
            'refresh_token.required' => 'El token de refresco es obligatorio.',
        ]);

    }
}
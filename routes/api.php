<?php

use App\Http\Controllers\Auth\AuthController;

use App\Http\Controllers\Rol\RolController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/validate-tokens', [AuthController::class, 'validateTokens']);

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['jwt.middleware', 'checkRoleMW:admin'])->group(function () {
    
    Route::get('/roles/index', [RolController::class, 'index']);

});


// RUTAS PARA VARIOS ROLES
Route::middleware(['jwt.middleware', 'checkRolesMW'])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

});


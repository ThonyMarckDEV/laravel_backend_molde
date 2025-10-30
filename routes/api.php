<?php

use App\Http\Controllers\Auth\AuthController;

use App\Http\Controllers\Auth\ResetPassword\PasswordResetController;
use App\Http\Controllers\ClienteController\ClienteController;
use App\Http\Controllers\EvaluacionCliente\EvaluacionClienteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/validate-tokens', [AuthController::class, 'validateTokens']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:admin'])->group(function () { 


});

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:usuario'])->group(function () { 


});


// RUTAS PARA VARIOS ROLES
Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () { 

    Route::post('/logout', [AuthController::class, 'logout']);

});


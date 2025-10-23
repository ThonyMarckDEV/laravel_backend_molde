<?php

use App\Http\Controllers\Auth\AuthController;

use App\Http\Controllers\Auth\ResetPassword\PasswordResetController;
use App\Http\Controllers\ClienteController\ClienteController;
use App\Http\Controllers\EvaluacionCliente\EvaluacionClienteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::post('/validate-refresh-token', [AuthController::class, 'validateRefreshToken']);

Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword']);

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:admin'])->group(function () { 


});

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:cliente'])->group(function () { 


});

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT) Y CHECKROLE (PARA VALIDAR ROL DEL TOKEN)
Route::middleware(['auth.jwt', 'checkRoleMW:asesor'])->group(function () { 

    Route::post('/evaluaciones/create', [EvaluacionClienteController::class, 'store']);
    Route::put('/evaluaciones/update/{evaluacionId}', [EvaluacionClienteController::class, 'update']);

});



// RUTAS PARA ROL ADMIN Y ASESOR
Route::middleware(['auth.jwt', 'CheckRolesMW_JEFE_NEGOCIOS_ASESOR'])->group(function () { 

    Route::get('/evaluaciones/index', [EvaluacionClienteController::class, 'index']);
    Route::get('/cliente/{dni}', [ClienteController::class, 'show']);
    Route::put('/evaluaciones/status/{evaluacionId}', [EvaluacionClienteController::class, 'updateStatus']);
    
});

// RUTAS PARA ROL ADMIN Y AUDITOR
Route::middleware(['auth.jwt', 'CheckRolesMW_ADMIN_AUDITOR'])->group(function () { 
    
   
});

// RUTAS PARA ROL ADMIN Y CLIENTE  Y AUDITOR
Route::middleware(['auth.jwt', 'CheckRolesMW_ADMIN_CLIENTE'])->group(function () { 
    

});

// RUTAS PARA VARIOS ROLES
Route::middleware(['auth.jwt', 'checkRolesMW'])->group(function () { 

    Route::post('/logout', [AuthController::class, 'logout']);

});


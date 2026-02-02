<?php

use App\Http\Controllers\Auth\AuthController;

use App\Http\Controllers\Rol\RolController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::post('/logout', [AuthController::class, 'logout']);

// RUTAS PARA cliente VALIDADA POR MIDDLEWARE AUTH (PARA TOKEN JWT)
Route::middleware(['jwt.middleware'])->group(function () {
    
    Route::get('/me', [AuthController::class, 'me']);

    //RUTAS ADMIN
    Route::middleware(['checkRoleMW:admin,superadmin'])->group(function () {
        
        Route::get('/roles/index', [RolController::class, 'index']);


    });


});


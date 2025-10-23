<?php

namespace App\Http\Controllers;

use App\Models\Datos;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Models\EvaluacionCliente as EvaluacionClienteModel;
use Illuminate\Support\Facades\Log;
use Throwable;

class UserController extends Controller
{
    public function getAllUsers(): JsonResponse
    {
        $users = User::all();
        return response()->json($users);
    }

}
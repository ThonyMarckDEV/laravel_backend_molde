<?php

namespace App\Http\Controllers\Rol;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RolController extends Controller
{
    /**
     * Muestra una lista paginada de roles.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Obtener roles paginados
            $roles = Rol::select(['id', 'nombre', 'descripcion', 'created_at']) // Selecciona solo lo necesario
                        ->paginate(10); // Puedes ajustar la paginación (ej. 10 por página)

            return response()->json($roles, 200);

        } catch (\Exception $e) {
            Log::error("Error al obtener roles: " . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener los roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
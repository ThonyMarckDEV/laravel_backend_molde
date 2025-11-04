<?php

namespace App\Http\Controllers\Rol;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RolController extends Controller
{
    /**
     * @OA\Get(
     *     path="/roles/index",
     *     summary="Listar roles paginados",
     *     description="Obtiene una lista de roles del sistema con paginación.",
     *     tags={"Roles"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Número de página a mostrar",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de roles obtenida correctamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="nombre", type="string", example="Administrador"),
     *                     @OA\Property(property="descripcion", type="string", example="Rol con todos los privilegios"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-28T15:00:00Z")
     *                 )
     *             ),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="total", type="integer", example=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado o token inválido"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al obtener los roles"
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $roles = Rol::select(['id', 'nombre', 'descripcion', 'created_at'])
                        ->paginate(10);

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

<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="API del Sistema de Moldes",
 *     version="1.0.0",
 *     description="Documentación generada con Swagger para la API del proyecto de Moldes Frameworks.",
 *     @OA\Contact(
 *         email="thonymarckdev@gmail.com",
 *         name="Antony Marck Mendoza Sanchez"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Servidor local de desarrollo"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Introduce tu token JWT. Ejemplo: 'Bearer {tu_token_aquí}'"
 * )
 */
abstract class Controller
{
    //
}

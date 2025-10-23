<?php

namespace App\Http\Controllers\Auth\ResetPassword;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Controllers\Auth\services\PasswordResetService;
use Illuminate\Support\Facades\Hash;

class PasswordResetController extends Controller
{
    /**
     * Handle the forgot password request.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'dni' => 'required|string|max:9',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'DNI inválido',
                'errors' => $validator->errors(),
            ], 400);
        }

        $user = User::with(['datos', 'datos.contactos'])
            ->whereHas('datos', function ($query) use ($request) {
                $query->where('dni', $request->dni);
            })
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'DNI no existe',
            ], 404);
        }

        if ($user->id_Rol !== 2) {
            return response()->json([
                'message' => 'Si no eres cliente y olvidaste tu contraseña, pídele al administrador que la cambie.',
            ], 403);
        }

        $existingTokenResult = PasswordResetService::checkExistingResetToken($user);
        if ($existingTokenResult) {
            return response()->json($existingTokenResult, $existingTokenResult['success'] ? 200 : 400);
        }

        $result = PasswordResetService::handlePasswordReset($user, $request->ip(), $request->userAgent());
        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Display the password reset form.
     * Note: Ensure your route parameter is named {id_Usuario} for consistency.
     *
     * @param string $id_Usuario
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function showResetForm($id_Usuario, $token)
    {
        $resetToken = DB::table('password_reset_tokens')
            ->where('id_Usuario', $id_Usuario)
            ->where('token', $token)
            ->first();

        if (!$resetToken) {
            return view('reset-password', [
                'error' => 'Enlace de restablecimiento inválido.',
                'id_Usuario' => $id_Usuario,
                'token' => $token,
            ]);
        }

        if (Carbon::parse($resetToken->expires_at)->isPast()) {
            return view('reset-password', [
                'error' => 'Enlace de restablecimiento expirado. Solicita un nuevo enlace.',
                'id_Usuario' => $id_Usuario,
                'token' => $token,
            ]);
        }

        return view('reset-password', [
            'id_Usuario' => $id_Usuario,
            'token' => $token,
        ]);
    }

    /**
     * Handle the password reset submission.
     * Note: Ensure your route parameter is named {id_Usuario} for consistency.
     *
     * @param Request $request
     * @param string $id_Usuario
     * @param string $token
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reset(Request $request, $id_Usuario, $token)
    {
        $resetToken = DB::table('password_reset_tokens')
            ->where('id_Usuario', $id_Usuario)
            ->where('token', $token)
            ->first();

        if (!$resetToken || Carbon::parse($resetToken->expires_at)->isPast()) {
            return redirect()->route('password.reset.form', ['id_Usuario' => $id_Usuario, 'token' => $token])
                ->with('error', 'Enlace de restablecimiento inválido o expirado.');
        }

        $user = User::with('datos')->find($id_Usuario);
        if (!$user) {
            return redirect()->route('password.reset.form', ['id_Usuario' => $id_Usuario, 'token' => $token])
                ->with('error', 'Usuario no encontrado.');
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $dni = $user->datos->dni ?? '';
        if ($request->password === $dni) {
            return redirect()->back()
                ->withErrors(['password' => 'La contraseña no puede ser tu DNI.'])
                ->withInput();
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_reset_tokens')
            ->where('id_Usuario', $user->id)
            ->delete();

        return redirect()->route('password.reset.form', ['id_Usuario' => $id_Usuario, 'token' => $token])
            ->with('success', 'Contraseña cambiada exitosamente. Por favor, inicia sesión.');
    }
}

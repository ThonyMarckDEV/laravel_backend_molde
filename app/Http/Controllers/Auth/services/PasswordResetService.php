<?php

namespace App\Http\Controllers\Auth\services;

use App\Mail\PasswordResetEmail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetService
{
    /**
     * Handle password reset token creation and email sending.
     *
     * @param User $user
     * @param string $ipAddress
     * @param string $userAgent
     * @return array
     */
    public static function handlePasswordReset(User $user, string $ipAddress, string $userAgent): array
    {
        // Generate reset token
        $resetToken = Str::random(60);
        $expiresAt = now()->addMinutes(10);

        // Delete existing tokens
        DB::table('password_reset_tokens')
            ->where('id_Usuario', $user->id)
            ->delete();

        // Store new reset token
        DB::table('password_reset_tokens')->insert([
            'id_Usuario' => $user->id,
            'token' => $resetToken,
            'ip_address' => $ipAddress,
            'device' => $userAgent,
            'expires_at' => $expiresAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Send reset email
        $resetUrl = config('app.url') . "/reset-password/{$user->id}/{$resetToken}";
        $contacto = $user->datos->contactos->first();

        if ($contacto && $contacto->correo) {
            Mail::to($contacto->correo)->send(new PasswordResetEmail($user, $resetUrl));
            return [
                'success' => true,
                'message' => 'Se ha enviado un correo para cambiar tu contraseña por seguridad. El enlace es válido por 10 minutos.',
            ];
        }

        return [
            'success' => false,
            'message' => 'No se pudo enviar el correo de restablecimiento. Contacta al soporte para actualizar tu correo.',
        ];
    }

    /**
     * Check for existing valid reset token and resend email if necessary.
     *
     * @param User $user
     * @return array|null
     */
    public static function checkExistingResetToken(User $user): ?array
    {
        $existingToken = DB::table('password_reset_tokens')
            ->where('id_Usuario', $user->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($existingToken) {
            $resetUrl = config('app.url') . "/reset-password/{$user->id}/{$existingToken->token}";
            $contacto = $user->datos->contactos->first();

            if ($contacto && $contacto->correo) {
                Mail::to($contacto->correo)->send(new PasswordResetEmail($user, $resetUrl));
                return [
                    'success' => true,
                    'message' => 'Se ha reenviado un correo para cambiar tu contraseña por seguridad. El enlace es válido por 10 minutos.',
                ];
            }

            return [
                'success' => false,
                'message' => 'No se pudo enviar el correo de restablecimiento. Contacta al soporte para actualizar tu correo.',
            ];
        }

        return null;
    }
}

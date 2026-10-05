<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminRecoveryNotification;
use App\UserRole;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Throwable;

/**
 * Recuperación de emergencia cuando el único ADMIN funcional queda bloqueado.
 *
 * - "Funcional" = rol ADMIN, activo (is_active) y no bloqueado (locked_at nulo).
 * - El token lo genera el broker de contraseñas de Laravel ('admin_recovery'):
 *   se guarda con hash en password_reset_tokens, vence a los 30 minutos y se
 *   elimina al usarse. Nunca se guarda ni se registra en texto plano.
 * - El enlace se envía solo al correo configurado en ADMIN_RECOVERY_EMAIL.
 */
class AdminRecovery
{
    public const BROKER = 'admin_recovery';

    public static function broker(): PasswordBroker
    {
        return Password::broker(self::BROKER);
    }

    /**
     * ¿Existe otro ADMIN activo y no bloqueado que pueda desbloquear a $user?
     */
    public static function anotherFunctionalAdminExists(User $user): bool
    {
        return User::query()
            ->where('id', '!=', $user->id)
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->whereNull('locked_at')
            ->exists();
    }

    /**
     * La recuperación de emergencia aplica solo a un ADMIN activo, bloqueado,
     * cuando no queda ningún otro administrador funcional.
     */
    public static function isNeeded(User $user): bool
    {
        return $user->role === UserRole::Admin
            && $user->is_active
            && $user->isLocked()
            && ! self::anotherFunctionalAdminExists($user);
    }

    /**
     * Envía un enlace nuevo si hace falta y no hay ya uno vigente.
     * Devuelve true solo si se envió un correo.
     */
    public static function sendIfNeeded(User $user): bool
    {
        if (! self::isNeeded($user) || self::hasValidToken($user)) {
            return false;
        }

        $recipient = trim((string) config('mekatos.admin_recovery_email'));

        if ($recipient === '') {
            Log::warning('Recuperación de ADMIN no enviada: ADMIN_RECOVERY_EMAIL no está configurado.', ['user_id' => $user->id]);

            return false;
        }

        $baseUrl = rtrim(trim((string) config('app.url')), '/');

        if ($baseUrl === '') {
            Log::warning('Recuperación de ADMIN no enviada: APP_URL no está configurado.', ['user_id' => $user->id]);

            return false;
        }

        $token = self::broker()->createToken($user);

        try {
            Notification::route('mail', $recipient)->notify(new AdminRecoveryNotification(
                adminEmail: $user->email,
                url: self::recoveryUrl($baseUrl, $token, $user->email),
                expiresInMinutes: self::expiresInMinutes(),
            ));
        } catch (Throwable $e) {
            // Sin correo el token no sirve: se elimina para poder reintentar más tarde.
            self::broker()->deleteToken($user);
            Log::error('No se pudo enviar el correo de recuperación de ADMIN.', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }

        return true;
    }

    /**
     * El enlace se arma sobre APP_URL y nunca sobre el host de la petición:
     * el cliente controla Host y X-Forwarded-Host, y podría hacer que el correo
     * legítimo apunte (con un token válido) a un dominio suyo.
     */
    private static function recoveryUrl(string $baseUrl, string $token, string $email): string
    {
        return $baseUrl.route('admin.recovery.show', ['token' => $token, 'email' => $email], false);
    }

    public static function invalidate(User $user): void
    {
        self::broker()->deleteToken($user);
    }

    public static function expiresInMinutes(): int
    {
        return (int) config('auth.passwords.'.self::BROKER.'.expire', 30);
    }

    /**
     * ¿Hay un token de recuperación emitido y todavía no vencido para $user?
     */
    private static function hasValidToken(User $user): bool
    {
        $table = config('auth.passwords.'.self::BROKER.'.table', 'password_reset_tokens');

        return DB::table($table)
            ->where('email', $user->getEmailForPasswordReset())
            ->where('created_at', '>', now()->subMinutes(self::expiresInMinutes()))
            ->exists();
    }
}

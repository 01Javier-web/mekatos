<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Verificación de credenciales con bloqueo por intentos fallidos.
 * La usan el login web y el de la API para que el bloqueo no pueda saltarse.
 *
 * - Cada contraseña incorrecta de un usuario existente suma 1 al contador.
 * - Al llegar a MAX_ATTEMPTS se guarda locked_at: el usuario queda bloqueado
 *   aunque después escriba la contraseña correcta.
 * - Un login correcto (activo y sin bloqueo) reinicia el contador a 0.
 * - El bloqueo es independiente de is_active.
 * - Para correos inexistentes no se modifica nada y la respuesta es la misma.
 */
class LoginAttempts
{
    public const MAX_ATTEMPTS = 3;

    public const GENERIC_ERROR = 'No fue posible iniciar sesión. Verifica tus datos; si el problema continúa, comunícate con un administrador.';

    /**
     * Devuelve el usuario solo si puede iniciar sesión; null en cualquier otro caso.
     */
    public static function attempt(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            // Tiempo de respuesta similar al de un usuario existente.
            Hash::check($password, '$2y$12$'.str_repeat('a', 53));

            return null;
        }

        if (! Hash::check($password, $user->password)) {
            self::registerFailure($user);

            return null;
        }

        if ($user->isLocked()) {
            // Puede ser el único ADMIN: si su enlace venció, se emite uno nuevo.
            AdminRecovery::sendIfNeeded($user);

            return null;
        }

        if (! $user->is_active) {
            return null;
        }

        if ($user->failed_login_attempts !== 0) {
            $user->forceFill(['failed_login_attempts' => 0])->save();
        }

        return $user;
    }

    public static function unlock(User $user): void
    {
        $user->forceFill(['failed_login_attempts' => 0, 'locked_at' => null])->save();
        AdminRecovery::invalidate($user);
    }

    private static function registerFailure(User $user): void
    {
        DB::transaction(function () use ($user): void {
            // Bloqueo de fila para que dos intentos simultáneos no se pierdan.
            $fresh = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($fresh->isLocked()) {
                return;
            }

            $attempts = min($fresh->failed_login_attempts + 1, self::MAX_ATTEMPTS);
            $data = ['failed_login_attempts' => $attempts];

            if ($attempts >= self::MAX_ATTEMPTS) {
                $data['locked_at'] = now();
            }

            $fresh->forceFill($data)->save();
        });

        $user->refresh();

        // Si quedó bloqueado (ahora o antes) y es el único ADMIN funcional,
        // se envía el enlace de recuperación (solo si no hay uno vigente).
        if ($user->isLocked()) {
            AdminRecovery::sendIfNeeded($user);
        }
    }
}

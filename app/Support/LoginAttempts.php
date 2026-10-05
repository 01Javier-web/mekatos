<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Verificación de credenciales con bloqueo por intentos fallidos.
 * La usan el login web y el de la API para que el bloqueo no pueda saltarse.
 *
 * - Cada contraseña incorrecta de un usuario existente suma 1 al contador, pero
 *   solo dentro de una ventana de WINDOW_MINUTES desde el primer fallo de la serie.
 *   Si la ventana ya venció, el fallo abre una ventana nueva y el contador vuelve a 1.
 * - Al llegar a MAX_ATTEMPTS dentro de la ventana se guarda locked_at: el usuario
 *   queda bloqueado aunque después escriba la contraseña correcta, hasta que un
 *   ADMIN lo desbloquee o se use la recuperación de ADMIN.
 * - Un login correcto (activo y sin bloqueo) reinicia el contador y la ventana.
 * - El bloqueo es independiente de is_active.
 * - Para correos inexistentes no se modifica nada y la respuesta es la misma.
 */
class LoginAttempts
{
    public const MAX_ATTEMPTS = 3;

    public const WINDOW_MINUTES = 15;

    public const THROTTLED_ERROR = 'Demasiados intentos de inicio de sesión. Espera un minuto e inténtalo de nuevo.';

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

        if ($user->failed_login_attempts === 0 && $user->failed_login_window_started_at === null) {
            return $user;
        }

        // Reinicio atómico y condicionado a que siga sin bloqueo: si un intento
        // fallido simultáneo acaba de bloquear la cuenta, este login no entra.
        $reset = User::query()
            ->whereKey($user->id)
            ->whereNull('locked_at')
            ->update(['failed_login_attempts' => 0, 'failed_login_window_started_at' => null]);

        if ($reset === 0) {
            return null;
        }

        return $user->refresh();
    }

    public static function unlock(User $user): void
    {
        $user->forceFill(['failed_login_attempts' => 0, 'failed_login_window_started_at' => null, 'locked_at' => null])->save();
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

            $now = now();
            $windowStart = $fresh->failed_login_window_started_at;

            // Sin fallos previos, sin ventana registrada o con la ventana vencida
            // (pasaron más de WINDOW_MINUTES desde su inicio): empieza una serie nueva.
            $newWindow = $fresh->failed_login_attempts === 0
                || $windowStart === null
                || $windowStart->lt($now->copy()->subMinutes(self::WINDOW_MINUTES));

            $attempts = $newWindow ? 1 : min($fresh->failed_login_attempts + 1, self::MAX_ATTEMPTS);
            $data = [
                'failed_login_attempts' => $attempts,
                'failed_login_window_started_at' => $newWindow ? $now : $windowStart,
            ];

            if ($attempts >= self::MAX_ATTEMPTS) {
                $data['locked_at'] = $now;
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

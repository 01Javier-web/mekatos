<?php

namespace App\Support;

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Garantiza que siempre quede al menos un ADMIN funcional.
 * La usan la gestión de usuarios web y la de la API para que no difieran.
 *
 * - Un ADMIN no puede desactivarse, quitarse el rol ni eliminarse a sí mismo.
 * - No se puede desactivar, degradar ni eliminar a un ADMIN activo si después
 *   no queda otro ADMIN funcional (activo y sin bloqueo por intentos fallidos).
 *
 * Las comprobaciones se hacen dentro de la transacción y sobre filas bloqueadas,
 * así dos peticiones simultáneas no pueden dejar el sistema sin administrador.
 */
class AdminAccountGuard
{
    public const SELF_DEACTIVATE = 'No puedes desactivar tu propio usuario. Pide a otro administrador que lo haga.';

    public const SELF_DEMOTE = 'No puedes quitarte el rol de administrador. Pide a otro administrador que lo haga.';

    public const SELF_DELETE = 'No puedes eliminar tu propio usuario.';

    public const LAST_ADMIN = 'No se puede completar la operación: es el último administrador activo y el sistema debe conservar al menos uno. Crea o activa otro administrador primero.';

    /**
     * Actualiza $target con $data solo si se respetan las reglas.
     */
    public static function update(User $actor, User $target, array $data): void
    {
        DB::transaction(function () use ($actor, $target, $data): void {
            [$current, $admins] = self::lock($target);

            $newRole = array_key_exists('role', $data)
                ? ($data['role'] instanceof UserRole ? $data['role'] : UserRole::from($data['role']))
                : $current->role;
            $newActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $current->is_active;

            if ($current->id === $actor->id) {
                if (! $newActive) {
                    throw ValidationException::withMessages(['is_active' => [self::SELF_DEACTIVATE]]);
                }

                if ($newRole !== UserRole::Admin) {
                    throw ValidationException::withMessages(['role' => [self::SELF_DEMOTE]]);
                }
            }

            $stopsBeingActiveAdmin = self::isActiveAdmin($current)
                && ($newRole !== UserRole::Admin || ! $newActive);

            if ($stopsBeingActiveAdmin && ! self::anotherFunctionalAdminExists($admins, $current)) {
                throw ValidationException::withMessages([
                    ($newActive ? 'role' : 'is_active') => [self::LAST_ADMIN],
                ]);
            }

            $target->update($data);
        });
    }

    /**
     * Ejecuta $delete (que elimina a $target) solo si se respetan las reglas.
     */
    public static function delete(User $actor, User $target, callable $delete): void
    {
        DB::transaction(function () use ($actor, $target, $delete): void {
            [$current, $admins] = self::lock($target);

            if ($current->id === $actor->id) {
                throw ValidationException::withMessages(['user' => [self::SELF_DELETE]]);
            }

            if (self::isActiveAdmin($current) && ! self::anotherFunctionalAdminExists($admins, $current)) {
                throw ValidationException::withMessages(['user' => [self::LAST_ADMIN]]);
            }

            $delete();
        });
    }

    /**
     * Bloquea (siempre en el mismo orden, para evitar interbloqueos) las filas de
     * los ADMIN y luego la del usuario afectado. Devuelve el estado real de ambos.
     *
     * @return array{0: User, 1: Collection<int, User>}
     */
    private static function lock(User $target): array
    {
        $admins = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $current = User::query()->lockForUpdate()->findOrFail($target->id);

        return [$current, $admins];
    }

    private static function isActiveAdmin(User $user): bool
    {
        return $user->role === UserRole::Admin && $user->is_active;
    }

    /**
     * Se evalúa sobre las filas ya bloqueadas (lectura actual, no una instantánea
     * anterior) para que otra petición no pueda colarse entre la comprobación y el cambio.
     */
    private static function anotherFunctionalAdminExists(Collection $admins, User $user): bool
    {
        return $admins->contains(
            fn (User $admin): bool => $admin->id !== $user->id && $admin->is_active && ! $admin->isLocked()
        );
    }
}

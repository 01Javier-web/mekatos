<?php

namespace App\Support;

use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\TableSessionStatus;

/**
 * Bloqueos de mesa y sesión de mesa en un único orden para todo el sistema:
 *
 *     mesa → sesión de mesa → pedidos
 *
 * Es el mismo orden que usa el cierre del día. Crear un pedido de mesa, agregarle
 * productos y cobrar la cuenta lo siguen, así ninguna de esas operaciones puede
 * cerrar o usar una sesión mientras otra la está modificando, y MySQL no entra en
 * interbloqueos por bloquear las mismas filas en orden distinto.
 *
 * Debe llamarse dentro de una transacción. Las lecturas con bloqueo devuelven el
 * estado más reciente confirmado, no una instantánea anterior.
 */
class TableSessionLock
{
    public const SESSION_CLOSED = 'La cuenta de esta mesa ya fue cerrada. Actualiza la cuenta o la pantalla e inténtalo de nuevo.';

    /**
     * Bloquea la mesa de la sesión y luego la sesión. Devuelve la sesión con su
     * estado actual (con la mesa cargada), o null si ya no existe.
     */
    public static function lockSession(int $sessionId): ?TableSession
    {
        // restaurant_table_id no cambia nunca, así que se puede leer sin bloqueo.
        $tableId = TableSession::query()->whereKey($sessionId)->value('restaurant_table_id');

        if ($tableId === null) {
            return null;
        }

        $table = RestaurantTable::query()->lockForUpdate()->find($tableId);
        $session = TableSession::query()->lockForUpdate()->find($sessionId);

        return $session?->setRelation('restaurantTable', $table);
    }

    /**
     * Con la mesa ya bloqueada, bloquea y devuelve su sesión activa (o null).
     */
    public static function lockActiveSessionOf(RestaurantTable $lockedTable): ?TableSession
    {
        return TableSession::query()
            ->where('restaurant_table_id', $lockedTable->id)
            ->where('status', TableSessionStatus::Active->value)
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    public static function isActive(?TableSession $session): bool
    {
        return $session !== null && $session->status === TableSessionStatus::Active;
    }
}

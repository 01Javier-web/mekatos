<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
use App\TableSessionStatus;
use App\TableStatus;

class TableController extends Controller
{
    /**
     * Obtiene una mesa a partir del token contenido
     * en su código QR.
     *
     * Consultar el QR no debe ocupar la mesa. La sesión se crea
     * únicamente cuando el cliente realmente envía un pedido.
     */
    public function show(string $token): JsonResponse
    {
        $table = RestaurantTable::query()
            ->where('qr_token', $token)
            ->firstOrFail();

        // Compatibilidad con sesiones creadas por la versión anterior:
        // si una sesión activa no tiene ningún pedido, es una sesión
        // vacía creada accidentalmente al abrir el QR. Se cierra y la
        // mesa vuelve a estar disponible.
        $session = $table->tableSessions()
            ->where('status', TableSessionStatus::Active)
            ->first();

        if ($session && ! $session->orders()->exists()) {
            $session->update([
                'status' => TableSessionStatus::CLOSED,
                'ended_at' => now(),
            ]);
            $session = null;
        }

        if (! $session && $table->status === TableStatus::OCCUPIED) {
            $table->update(['status' => TableStatus::AVAILABLE]);
        }

        return response()->json([
            'id' => $table->id,
            'number' => $table->number,
            'status' => $table->status->value,
            'session_id' => $session?->id,
        ]);
    }
}

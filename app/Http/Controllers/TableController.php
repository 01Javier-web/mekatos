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
     */
    public function show(string $token): JsonResponse
    {
        // Buscar la mesa mediante el token del QR.
        $table = RestaurantTable::query()
            ->where('qr_token', $token)
            ->firstOrFail();

        // Buscar si la mesa ya tiene una sesión activa.
        $session = $table->tableSessions()
            ->where('status', TableSessionStatus::Active)
            ->first();

        // Si no existe una sesión activa, crear una nueva.
        if (!$session) {
            $session = $table->tableSessions()->create([
                'status' => TableSessionStatus::Active,
                'started_at' => now(),
            ]);
        }

        // Marcar la mesa como ocupada.
        $table->update([
            'status' => TableStatus::OCCUPIED,
        ]);

        return response()->json([
            'id' => $table->id,
            'number' => $table->number,
            'status' => $table->status->value,
            'session_id' => $session->id,
        ]);
    }
}
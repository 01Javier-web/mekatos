<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use Illuminate\Http\JsonResponse;
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

        return response()->json([
            'id' => $table->id,
            'number' => $table->number,
            'status' => $table->status->value,
            'session_id' => null,
        ]);
    }
}

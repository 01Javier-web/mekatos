<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use App\TableStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RestaurantTableController extends Controller
{
    public function index(): JsonResponse
    {
        $tables = RestaurantTable::query()
            ->latest()
            ->get();

        return response()->json($tables);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'integer', 'unique:restaurant_tables,number'],
            'name' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1'],
            'qr_token' => ['required', 'string', 'max:255', 'unique:restaurant_tables,qr_token'],
            'status' => ['sometimes', Rule::enum(TableStatus::class)],
        ]);

        $table = RestaurantTable::create([
            'number' => $validated['number'],
            'name' => $validated['name'] ?? null,
            'capacity' => $validated['capacity'],
            'qr_token' => $validated['qr_token'],
            'status' => $validated['status'] ?? TableStatus::AVAILABLE,
        ]);

        return response()->json([
            'message' => 'Mesa creada exitosamente',
            'table' => $table,
        ], 201);
    }

    public function show(RestaurantTable $restaurantTable): JsonResponse
    {
        return response()->json($restaurantTable);
    }

    public function update(
        Request $request,
        RestaurantTable $restaurantTable
    ): JsonResponse {
        $validated = $request->validate([
            'number' => [
                'sometimes',
                'integer',
                Rule::unique('restaurant_tables', 'number')
                    ->ignore($restaurantTable->id),
            ],
            'name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'capacity' => ['sometimes', 'integer', 'min:1'],
            'qr_token' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('restaurant_tables', 'qr_token')
                    ->ignore($restaurantTable->id),
            ],
            'status' => ['sometimes', Rule::enum(TableStatus::class)],
        ]);

        $restaurantTable->update($validated);

        return response()->json([
            'message' => 'Mesa actualizada exitosamente',
            'table' => $restaurantTable,
        ]);
    }

    public function destroy(RestaurantTable $restaurantTable): JsonResponse
    {
        $restaurantTable->delete();

        return response()->json([
            'message' => 'Mesa eliminada exitosamente',
        ]);
    }
}
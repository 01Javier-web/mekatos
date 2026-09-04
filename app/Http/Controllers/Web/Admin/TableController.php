<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TableController extends Controller
{
    public function index(): View
    {
        return view('admin.tables.index', [
            'tables' => RestaurantTable::query()->with(['tableSessions' => fn ($query) => $query->latest('id')->limit(1)])->orderBy('number')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.tables.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'integer', 'unique:restaurant_tables,number'],
            'name' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(TableStatus::class)],
        ]);

        RestaurantTable::create([
            'number' => $validated['number'],
            'name' => $validated['name'] ?? null,
            'capacity' => $validated['capacity'],
            'qr_token' => Str::uuid()->toString(),
            'status' => $validated['status'] ?? TableStatus::AVAILABLE,
        ]);

        return redirect()->route('admin.tables.index')->with('success', 'Mesa creada exitosamente.');
    }

    public function edit(RestaurantTable $restaurantTable): View
    {
        return view('admin.tables.edit', ['table' => $restaurantTable]);
    }

    public function update(Request $request, RestaurantTable $restaurantTable): RedirectResponse
    {
        $validated = $request->validate([
            'number' => ['required', 'integer', Rule::unique('restaurant_tables', 'number')->ignore($restaurantTable->id)],
            'name' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1'],
            'status' => ['required', Rule::enum(TableStatus::class)],
        ]);

        $restaurantTable->update($validated);

        return redirect()->route('admin.tables.index')->with('success', 'Mesa actualizada exitosamente.');
    }

    public function destroy(RestaurantTable $restaurantTable): RedirectResponse
    {
        $restaurantTable->delete();

        return redirect()->route('admin.tables.index')->with('success', 'Mesa eliminada exitosamente.');
    }
}

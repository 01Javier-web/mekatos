<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\TableSessionStatus;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TableController extends Controller
{
    public function index(): View
    {
        $host = request()->getHost();

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $resolvedHost = gethostbyname(gethostname());
            if ($resolvedHost !== gethostname()) {
                $host = $resolvedHost;
            }
        }

        $qrBaseUrl = request()->getScheme().'://'.$host;
        if (request()->getPort() && !in_array(request()->getPort(), [80, 443], true)) {
            $qrBaseUrl .= ':'.request()->getPort();
        }

        return view('admin.tables.index', [
            'tables' => RestaurantTable::query()->orderBy('number')->get(),
            'qrBaseUrl' => $qrBaseUrl,
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

    public function release(RestaurantTable $restaurantTable): RedirectResponse
    {
        $session = $restaurantTable->tableSessions()
            ->where('status', TableSessionStatus::Active)
            ->first();

        if ($session && $session->orders()->exists()) {
            return redirect()->route('admin.tables.index')
                ->with('error', 'No se puede liberar esta mesa porque tiene pedidos asociados.');
        }

        DB::transaction(function () use ($restaurantTable, $session): void {
            if ($session) {
                $session->update([
                    'status' => TableSessionStatus::CLOSED,
                    'ended_at' => now(),
                ]);
            }

            $restaurantTable->update([
                'status' => TableStatus::AVAILABLE,
            ]);
        });

        return redirect()->route('admin.tables.index')
            ->with('success', "Mesa {$restaurantTable->number} marcada como libre.");
    }

    public function destroy(RestaurantTable $restaurantTable): RedirectResponse
    {
        $restaurantTable->delete();

        return redirect()->route('admin.tables.index')->with('success', 'Mesa eliminada exitosamente.');
    }
}

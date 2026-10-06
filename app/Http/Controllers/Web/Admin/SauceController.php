<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sauce;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Salsas: el ADMIN las activa o desactiva. No se eliminan, para conservar los
 * pedidos que ya las usaron.
 */
class SauceController extends Controller
{
    public function index(): View
    {
        return view('admin.sauces.index', [
            'sauces' => Sauce::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function toggle(Sauce $sauce): RedirectResponse
    {
        $sauce->update(['is_active' => ! $sauce->is_active]);

        return redirect()->route('admin.sauces.index')->with(
            'success',
            $sauce->is_active
                ? "La salsa {$sauce->name} quedó activa."
                : "La salsa {$sauce->name} quedó desactivada."
        );
    }
}

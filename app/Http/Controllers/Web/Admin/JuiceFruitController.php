<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\JuiceFruit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JuiceFruitController extends Controller
{
    public function index(): View
    {
        return view('admin.juice-fruits.index', [
            'fruits' => JuiceFruit::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function toggle(JuiceFruit $juiceFruit): RedirectResponse
    {
        $juiceFruit->update(['is_available' => ! $juiceFruit->is_available]);

        return redirect()->route('admin.juice-fruits.index')->with(
            'success',
            $juiceFruit->is_available
                ? "La fruta {$juiceFruit->name} quedó disponible."
                : "La fruta {$juiceFruit->name} quedó agotada."
        );
    }
}

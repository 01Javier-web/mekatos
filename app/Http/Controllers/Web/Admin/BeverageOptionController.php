<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BeverageOption;
use App\Models\Product;
use App\Support\BeverageOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BeverageOptionController extends Controller
{
    public function index(Product $product): View|RedirectResponse
    {
        if (! BeverageOptions::hasOptions($product)) {
            return redirect()->route('admin.products.edit', $product)
                ->with('error', 'Este producto no tiene opciones configurables.');
        }

        $options = BeverageOption::query()
            ->where('product_id', $product->id)
            ->orderBy('sort_order')
            ->get();

        return view('admin.beverage-options.index', [
            'product' => $product,
            'options' => $options,
        ]);
    }

    public function toggle(Product $product, BeverageOption $beverageOption): RedirectResponse
    {
        if ($beverageOption->product_id !== $product->id) {
            abort(404);
        }

        $beverageOption->update([
            'is_available' => ! $beverageOption->is_available,
        ]);

        return redirect()->route('admin.beverage-options.index', $product)
            ->with('success', $beverageOption->is_available
                ? "{$beverageOption->name} está disponible nuevamente."
                : "{$beverageOption->name} fue marcado como agotado.");
    }
}

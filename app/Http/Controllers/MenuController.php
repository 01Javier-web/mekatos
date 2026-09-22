<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\ComboOptions;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::with([
            'products' => function ($query) {
                $query
                    ->where('is_available', true)
                    ->where(function ($products) {
                        $products
                            ->whereDoesntHave('beverageOptions')
                            ->orWhereHas('beverageOptions', fn ($options) => $options->where('is_available', true));
                    })
                    ->with([
                        'beverageOptions' => function ($options) {
                            $options->where('is_available', true)->orderBy('sort_order');
                        },
                    ]);
            },
        ])->get();

        $comboBeverages = [];

        foreach (ComboOptions::types() as $type => $label) {
            $comboBeverages[$type] = [
                'label' => $label,
                'flavors' => ComboOptions::availableFlavors($type),
            ];
        }

        return response()->json([
            'categories' => $categories,
            'combo' => [
                'price' => ComboOptions::PRICE,
                'beverages' => $comboBeverages,
            ],
        ]);
    }
}

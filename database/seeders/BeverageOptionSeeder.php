<?php

namespace Database\Seeders;

use App\Models\BeverageOption;
use App\Models\Product;
use App\Support\BeverageOptions;
use Illuminate\Database\Seeder;

class BeverageOptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (BeverageOptions::PRODUCTS as $productName => $optionNames) {
            $product = Product::query()->where('name', $productName)->first();

            if (! $product) {
                continue;
            }

            foreach ($optionNames as $sortOrder => $optionName) {
                BeverageOption::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'name' => $optionName,
                    ],
                    [
                        'sort_order' => $sortOrder + 1,
                        'is_available' => true,
                    ]
                );
            }
        }

        BeverageOption::query()
            ->whereNotIn('product_id', Product::query()->whereIn('name', array_keys(BeverageOptions::PRODUCTS))->pluck('id'))
            ->delete();
    }
}

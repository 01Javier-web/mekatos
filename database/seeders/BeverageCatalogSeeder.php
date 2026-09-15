<?php

namespace Database\Seeders;

use App\Models\BeverageOption;
use App\Models\Category;
use App\Models\Product;
use App\Support\BeverageOptions;
use Illuminate\Database\Seeder;

class BeverageCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Jugos y Bebidas Preparadas' => [
                'description' => 'Jugos, bebidas preparadas y otras bebidas.',
                'sort_order' => 16,
                'products' => [
                    ['name' => 'Jugo Natural Jarra', 'price' => 8500, 'description' => 'En agua $8.500 o en leche $9.500. Selecciona la fruta al pedir.'],
                    ['name' => 'Limonada Jarra', 'price' => 6500],
                    ['name' => 'Milo Jarra', 'price' => 10000],
                    ['name' => 'Tamarindo Preparada', 'price' => 5500],
                    ['name' => 'Jugos Hit', 'price' => 4500],
                ],
            ],
            'Gaseosas y Agua' => [
                'description' => 'Gaseosas, agua y bebidas embotelladas.',
                'sort_order' => 17,
                'products' => [
                    ['name' => 'Gaseosa 350 ml', 'price' => 4500],
                    ['name' => 'Gaseosa 400 ml', 'price' => 4500],
                    ['name' => 'Gaseosa 1.5 L', 'price' => 9500],
                    ['name' => 'Agua con gas 600 ml', 'price' => 3500],
                    ['name' => 'Agua sin gas 600 ml', 'price' => 3500],
                    ['name' => 'H2O limón', 'price' => 4500],
                    ['name' => 'H2O maracuyá', 'price' => 4500],
                    ['name' => 'Gatorade rojo', 'price' => 5000],
                ],
            ],
            'Cerveza' => [
                'description' => 'Cervezas disponibles.',
                'sort_order' => 18,
                'products' => [
                    ['name' => 'Cerveza', 'price' => 5000],
                ],
            ],
        ];

        foreach ($categories as $categoryName => $categoryData) {
            $category = Category::updateOrCreate(
                ['name' => $categoryName],
                [
                    'description' => $categoryData['description'],
                    'sort_order' => $categoryData['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($categoryData['products'] as $productData) {
                $product = Product::firstOrNew(['name' => $productData['name']]);
                $product->category_id = $category->id;
                if (array_key_exists('description', $productData)) {
                    $product->description = $productData['description'];
                }
                $product->price = $productData['price'];
                if (! $product->exists) {
                    $product->is_available = true;
                }
                $product->save();
            }
        }

        Product::query()->whereIn('name', ['Gaseosa 1.5', 'Agua Botella'])->update(['is_available' => false]);

        foreach (BeverageOptions::PRODUCTS as $productName => $optionNames) {
            $product = Product::query()->where('name', $productName)->first();
            if (! $product) {
                continue;
            }

            foreach ($optionNames as $sortOrder => $optionName) {
                $option = BeverageOption::query()->firstOrCreate(
                    ['product_id' => $product->id, 'name' => $optionName],
                    ['sort_order' => $sortOrder + 1, 'is_available' => true]
                );
                $option->update(['sort_order' => $sortOrder + 1]);
            }
        }
    }
}

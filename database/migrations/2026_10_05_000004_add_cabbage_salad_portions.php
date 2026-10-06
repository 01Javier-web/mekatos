<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Porciones de ensalada de repollo (mismo mecanismo que 2026_10_03_000001_add_portions_to_products_and_order_items):
 * se agregan a la categoría PORCIONES sin duplicar productos existentes.
 */
return new class extends Migration
{
    private const PORTIONS = [
        ['name' => 'Porción de ensalada de repollo', 'price' => 2500],
        ['name' => 'Medio litro de ensalada de repollo', 'price' => 20000],
        ['name' => 'Un litro de ensalada de repollo', 'price' => 30000],
    ];

    public function up(): void
    {
        $category = DB::table('categories')
            ->whereRaw('LOWER(name) = ?', ['porciones'])
            ->first();

        if (! $category) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => 'PORCIONES',
                'description' => 'Porciones independientes o para acompañar otros productos.',
                'sort_order' => 999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $categoryId = $category->id;
        }

        foreach (self::PORTIONS as $portion) {
            $product = DB::table('products')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($portion['name'])])
                ->first();

            if ($product) {
                DB::table('products')->where('id', $product->id)->update([
                    'category_id' => $categoryId,
                    'price' => $portion['price'],
                    'is_portion' => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('products')->insert([
                    'category_id' => $categoryId,
                    'name' => $portion['name'],
                    'description' => null,
                    'price' => $portion['price'],
                    'image_path' => null,
                    'is_available' => true,
                    'is_portion' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Solo quita estas porciones si no se han usado en pedidos.
        DB::table('products')
            ->whereIn('name', array_column(self::PORTIONS, 'name'))
            ->where('is_portion', true)
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('order_items')->whereColumn('order_items.product_id', 'products.id'))
            ->delete();
    }
};

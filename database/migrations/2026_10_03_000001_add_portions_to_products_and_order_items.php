<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_portion')->default(false)->after('is_available');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('paired_order_item_id')
                ->nullable()
                ->after('sent_at')
                ->constrained('order_items')
                ->nullOnDelete();

            $table->index('paired_order_item_id');
        });

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

        $portions = [
            ['name' => 'Papa francesa', 'price' => 8500],
            ['name' => 'Papa criolla', 'price' => 7500],
            ['name' => 'Huevos x 10', 'price' => 7500],
            ['name' => 'Tocineta', 'price' => 7500],
            ['name' => 'Salchicha', 'price' => 7500],
            ['name' => 'Yuca', 'price' => 4500],
            ['name' => 'Quesillo tajada', 'price' => 1500],
        ];

        foreach ($portions as $portion) {
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
        $category = DB::table('categories')
            ->whereRaw('LOWER(name) = ?', ['porciones'])
            ->first();

        if ($category) {
            DB::table('products')
                ->where('category_id', $category->id)
                ->where('is_portion', true)
                ->delete();

            DB::table('categories')->where('id', $category->id)->delete();
        }

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['paired_order_item_id']);
            $table->dropIndex(['paired_order_item_id']);
            $table->dropColumn('paired_order_item_id');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_portion');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indica de forma explícita qué productos admiten salsas (independiente del icopor).
 *
 * Por defecto ningún producto admite salsas. Solo para los productos ya existentes se
 * marcan como "permite salsas" los que no son bebidas (las categorías de bebidas son
 * las mismas que separa la comanda). Después, el ADMIN lo configura en cada producto.
 *
 * No modifica ni borra ningún otro dato.
 */
return new class extends Migration
{
    private const BEVERAGE_CATEGORIES = [
        'jugos y bebidas preparadas',
        'gaseosas y agua',
        'cerveza',
        'granizadas',
    ];

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('allows_sauces')->default(false)->after('is_portion');
        });

        $beverageCategoryIds = DB::table('categories')
            ->whereIn(DB::raw('LOWER(name)'), self::BEVERAGE_CATEGORIES)
            ->pluck('id');

        DB::table('products')
            ->whereNotIn('category_id', $beverageCategoryIds)
            ->update(['allows_sauces' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('allows_sauces');
        });
    }
};

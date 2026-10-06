<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Piña sobre" no es una salsa distinta: era un duplicado de "Piña" en el catálogo inicial
 * (2026_10_05_000002_create_sauces_tables). Se retira sin tocar ninguna otra salsa.
 *
 * - Sin pedidos que la usen: se elimina.
 * - Con pedidos que la usen: se desactiva (deja de ofrecerse) para conservar esos pedidos;
 *   order_sauces.sauce_id es restrictOnDelete, así que nunca se borra una salsa en uso.
 */
return new class extends Migration
{
    private const NAME = 'Piña sobre';

    public function up(): void
    {
        $sauce = DB::table('sauces')->where('name', self::NAME)->first();

        if (! $sauce) {
            return;
        }

        if (DB::table('order_sauces')->where('sauce_id', $sauce->id)->exists()) {
            DB::table('sauces')->where('id', $sauce->id)->update(['is_active' => false, 'updated_at' => now()]);

            return;
        }

        DB::table('sauces')->where('id', $sauce->id)->delete();
    }

    public function down(): void
    {
        // Vuelve a dejarla como estaba en el catálogo inicial, sin duplicarla.
        DB::table('sauces')->insertOrIgnore([
            'name' => self::NAME,
            'sort_order' => 10,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sauces')->where('name', self::NAME)->update(['is_active' => true, 'updated_at' => now()]);
    }
};

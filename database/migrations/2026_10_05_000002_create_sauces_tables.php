<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Salsas para pedidos PARA_LLEVAR y DOMICILIO.
 *
 * - sauces: catálogo de salsas (se activan/desactivan, nunca se borran).
 * - order_sauces: salsas elegidas en un pedido. Con order_item_id son salsas de un
 *   producto (EN_PRODUCTO o APARTE); sin order_item_id son salsas generales (siempre
 *   APARTE). order_round_id indica en qué impresión (ronda) salen.
 *
 * Solo crea tablas nuevas e inserta las salsas iniciales sin duplicar: no modifica
 * ni borra datos existentes.
 */
return new class extends Migration
{
    private const SAUCES = [
        'Tomate', 'Rosada', 'Tártara', 'Aderezo', 'Española', 'Repollo', 'Mostaza', 'Piña',
        'Piña casera', 'Piña sobre', 'Mayonesa', 'BBQ', 'Maíz', 'Cebolla', 'Ripio de papa',
        'Miel', 'Chimichurri',
    ];

    public function up(): void
    {
        Schema::create('sauces', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 60)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach (self::SAUCES as $index => $name) {
            // insertOrIgnore + nombre único: no crea duplicados si alguna ya existe.
            DB::table('sauces')->insertOrIgnore([
                'name' => $name,
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('order_sauces', function (Blueprint $table): void {
            $table->id();
            // Se eliminan junto con el pedido/línea/ronda (por ejemplo, en el cierre del día).
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('order_round_id')->nullable()->constrained('order_rounds')->cascadeOnDelete();
            // Una salsa nunca se borra mientras haya pedidos que la usen.
            $table->foreignId('sauce_id')->constrained('sauces')->restrictOnDelete();
            $table->string('placement', 20);
            $table->timestamps();

            $table->unique(['order_item_id', 'sauce_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sauces');
        Schema::dropIfExists('sauces');
    }
};

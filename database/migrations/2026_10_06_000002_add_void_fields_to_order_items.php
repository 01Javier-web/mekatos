<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Líneas y salsas generales anuladas por "✏️ Editar pedido".
 *
 * order_items — una línea anulada no se borra: conserva producto, cantidad, precio, notas,
 * salsas y su ronda original, pero deja de contar en totales, cuenta y ventas.
 * - voided_at / voided_by_user_id: cuándo y quién la anuló.
 * - voided_round_id: la edición (ronda) en la que se anuló.
 * - void_sent_at: cuándo se imprimió el "❌ NO PREPARAR" (solo si la línea ya se había
 *   enviado a cocina; vacío = cocina todavía no fue avisada).
 *
 * order_sauces — solo para las salsas generales (sin order_item_id); las salsas por producto
 * siguen dependiendo de su línea y dejan estas columnas vacías.
 * - sent_at: cuándo se envió a cocina (se marca al imprimir, igual que las líneas).
 * - voided_at: anulada por una edición después de enviarse (una no enviada se borra).
 * - void_sent_at: cuándo se imprimió su "❌ NO PREPARAR".
 * Las salsas generales existentes cuya ronda ya se imprimió recuperan sent_at (la hora de esa
 * impresión); las de rondas nunca impresas quedan sin enviar.
 *
 * Solo agrega columnas opcionales: no borra ni modifica otros datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_items', 'voided_at')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->timestamp('voided_at')->nullable()->after('paired_order_item_id');
                $table->foreignId('voided_by_user_id')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
                $table->foreignId('voided_round_id')->nullable()->after('voided_by_user_id')->constrained('order_rounds')->nullOnDelete();
                $table->timestamp('void_sent_at')->nullable()->after('voided_round_id');
            });
        }

        if (! Schema::hasColumn('order_sauces', 'sent_at')) {
            Schema::table('order_sauces', function (Blueprint $table): void {
                $table->timestamp('sent_at')->nullable()->after('placement');
                $table->timestamp('voided_at')->nullable()->after('sent_at');
                $table->timestamp('void_sent_at')->nullable()->after('voided_at');
            });
        }

        // Rondas ya impresas: la primera hora de envío de sus líneas.
        $printedRounds = DB::table('order_items')
            ->whereNotNull('order_round_id')
            ->whereNotNull('sent_at')
            ->groupBy('order_round_id')
            ->selectRaw('order_round_id, MIN(sent_at) as sent_at')
            ->pluck('sent_at', 'order_round_id');

        foreach ($printedRounds as $roundId => $sentAt) {
            DB::table('order_sauces')
                ->whereNull('order_item_id')
                ->where('order_round_id', $roundId)
                ->whereNull('sent_at')
                ->update(['sent_at' => $sentAt]);
        }
    }

    public function down(): void
    {
        Schema::table('order_sauces', function (Blueprint $table): void {
            $table->dropColumn(['sent_at', 'voided_at', 'void_sent_at']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('voided_round_id');
            $table->dropConstrainedForeignId('voided_by_user_id');
            $table->dropColumn(['voided_at', 'void_sent_at']);
        });
    }
};

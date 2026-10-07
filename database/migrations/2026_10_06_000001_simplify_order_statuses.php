<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estados simplificados: PENDIENTE → POR COBRAR → TERMINADO (+ CANCELADO).
 *
 * - "🛵 Salió" deja de ser un estado: se guarda como marca en dispatched_at / dispatched_by_user_id.
 * - Los pedidos en ENTREGADO, EN PREPARACIÓN o EN CAMINO pasan a POR COBRAR.
 * - La marca de salida de los domicilios anteriores se recupera solo con evidencia exacta:
 *   1. el evento que registraba el "Salió" anterior en el historial (nota 'Domicilio salió.'),
 *      con su fecha y su usuario;
 *   2. para los domicilios heredados EN CAMINO (estado que el flujo nuevo nunca asigna), con
 *      delivered_at / delivered_by_user_id.
 *   Un domicilio solo impreso (POR COBRAR sin ese evento) nunca se marca como salido, aunque
 *   la migración se ejecute después de haber usado el flujo nuevo.
 *
 * No borra pedidos ni historial (order_status_histories conserva los valores anteriores),
 * y no toca productos, salsas ni icopor.
 */
return new class extends Migration
{
    private const LEGACY_TO_COLLECT = ['ENTREGADO', 'EN PREPARACIÓN', 'EN CAMINO'];

    /** Nota exacta con la que el "🛵 Salió" anterior registraba la salida en el historial. */
    private const LEGACY_DISPATCH_NOTE = 'Domicilio salió.';

    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'dispatched_at')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->timestamp('dispatched_at')->nullable()->after('delivered_at');
                $table->foreignId('dispatched_by_user_id')->nullable()->after('dispatched_at')->constrained('users')->nullOnDelete();
            });
        }

        // 1. Evento "Salió" del flujo anterior: fecha y usuario del propio evento (el primero
        //    de cada pedido; solo se podía marcar una vez).
        $events = DB::table('order_status_histories as h')
            ->join('orders as o', 'o.id', '=', 'h.order_id')
            ->where('o.type', 'DOMICILIO')
            ->whereNull('o.dispatched_at')
            ->where('h.notes', self::LEGACY_DISPATCH_NOTE)
            ->orderByDesc('h.id')
            ->get(['h.order_id', 'h.changed_at', 'h.changed_by_user_id'])
            ->keyBy('order_id');

        foreach ($events as $event) {
            DB::table('orders')->where('id', $event->order_id)->update([
                'dispatched_at' => $event->changed_at,
                'dispatched_by_user_id' => $event->changed_by_user_id,
            ]);
        }

        // 2. Domicilios heredados EN CAMINO sin ese evento (antes de normalizar su estado).
        DB::table('orders')
            ->where('type', 'DOMICILIO')
            ->where('status', 'EN CAMINO')
            ->whereNull('dispatched_at')
            ->update([
                'dispatched_at' => DB::raw('delivered_at'),
                'dispatched_by_user_id' => DB::raw('delivered_by_user_id'),
            ]);

        DB::table('orders')
            ->whereIn('status', self::LEGACY_TO_COLLECT)
            ->update(['status' => 'POR COBRAR', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Los estados anteriores no se pueden reconstruir con certeza (POR COBRAR ya existía);
        // solo se quitan las columnas nuevas.
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dispatched_by_user_id');
            $table->dropColumn('dispatched_at');
        });
    }
};

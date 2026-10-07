<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimplifyOrderStatusesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_06_000001_simplify_order_statuses.php');
    }

    private function insertOrder(string $type, string $status, ?int $deliveredBy = null): int
    {
        return DB::table('orders')->insertGetId([
            'type' => $type, 'status' => $status, 'subtotal' => 1000, 'packaging_fee' => 0, 'delivery_fee' => 0, 'tax' => 0, 'total' => 1000,
            'delivered_by_user_id' => $deliveredBy, 'delivered_at' => $deliveredBy ? '2026-10-05 12:30:00' : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function history(int $orderId, ?string $previous, string $new, ?int $userId, string $at, ?string $notes): void
    {
        DB::table('order_status_histories')->insert(['order_id' => $orderId, 'previous_status' => $previous, 'new_status' => $new, 'changed_by_user_id' => $userId, 'changed_at' => $at, 'notes' => $notes]);
    }

    public function test_migration_normalizes_legacy_states_and_recovers_the_dispatch_mark_only_with_exact_evidence(): void
    {
        $cashier = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $rider = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        // Las columnas ya existen (RefreshDatabase corrió la migración); se insertan datos con el
        // flujo anterior y se vuelve a ejecutar up(), que solo agrega columnas si faltan.
        $this->assertTrue(Schema::hasColumn('orders', 'dispatched_at'));
        $this->assertTrue(Schema::hasColumn('orders', 'dispatched_by_user_id'));

        $ids = [
            'entregado_mesa' => $this->insertOrder('MESA', 'ENTREGADO', $cashier->id),
            'preparacion_llevar' => $this->insertOrder('PARA_LLEVAR', 'EN PREPARACIÓN'),
            'entregado_domicilio' => $this->insertOrder('DOMICILIO', 'ENTREGADO', $cashier->id),
            'en_camino_domicilio' => $this->insertOrder('DOMICILIO', 'EN CAMINO', $rider->id),
            // Salió con el flujo anterior: el evento tiene la hora y el usuario reales.
            'salio_por_cobrar' => $this->insertOrder('DOMICILIO', 'POR COBRAR', $rider->id),
            'salio_terminado' => $this->insertOrder('DOMICILIO', 'TERMINADO', $rider->id),
            // Solo impreso con el flujo nuevo: POR COBRAR, pero sin evento de salida.
            'impreso_nuevo_flujo' => $this->insertOrder('DOMICILIO', 'POR COBRAR', $cashier->id),
            'por_cobrar_llevar' => $this->insertOrder('PARA_LLEVAR', 'POR COBRAR', $cashier->id),
            'pendiente' => $this->insertOrder('PARA_LLEVAR', 'PENDIENTE'),
            'cancelado' => $this->insertOrder('PARA_LLEVAR', 'CANCELADO'),
        ];
        $this->history($ids['entregado_mesa'], 'PENDIENTE', 'ENTREGADO', $cashier->id, '2026-10-05 12:00:00', 'Comandas impresas.');
        $this->history($ids['salio_por_cobrar'], 'PENDIENTE', 'ENTREGADO', $cashier->id, '2026-10-05 11:00:00', 'Comandas impresas.');
        $this->history($ids['salio_por_cobrar'], 'ENTREGADO', 'POR COBRAR', $rider->id, '2026-10-05 11:20:00', 'Domicilio salió.');
        $this->history($ids['salio_terminado'], 'ENTREGADO', 'POR COBRAR', $rider->id, '2026-10-05 10:15:00', 'Domicilio salió.');
        $this->history($ids['salio_terminado'], 'POR COBRAR', 'TERMINADO', $cashier->id, '2026-10-05 10:40:00', null);
        $this->history($ids['impreso_nuevo_flujo'], 'PENDIENTE', 'POR COBRAR', $cashier->id, '2026-10-05 13:00:00', 'Comandas impresas.');
        $historyBefore = DB::table('order_status_histories')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $this->migration()->up();

        $row = fn (string $key) => DB::table('orders')->find($ids[$key]);
        foreach (['entregado_mesa', 'preparacion_llevar', 'entregado_domicilio', 'en_camino_domicilio', 'salio_por_cobrar', 'impreso_nuevo_flujo', 'por_cobrar_llevar'] as $key) {
            $this->assertSame('POR COBRAR', $row($key)->status, $key);
        }
        $this->assertSame('PENDIENTE', $row('pendiente')->status);
        $this->assertSame('TERMINADO', $row('salio_terminado')->status);
        $this->assertSame('CANCELADO', $row('cancelado')->status);

        // Marca recuperada del evento exacto (hora y usuario del evento, no de delivered_*).
        $this->assertSame('2026-10-05 11:20:00', (string) $row('salio_por_cobrar')->dispatched_at);
        $this->assertSame($rider->id, (int) $row('salio_por_cobrar')->dispatched_by_user_id);
        $this->assertSame('2026-10-05 10:15:00', (string) $row('salio_terminado')->dispatched_at);
        $this->assertSame($rider->id, (int) $row('salio_terminado')->dispatched_by_user_id);
        // EN CAMINO heredado: respaldo con delivered_*.
        $this->assertSame('2026-10-05 12:30:00', (string) $row('en_camino_domicilio')->dispatched_at);
        $this->assertSame($rider->id, (int) $row('en_camino_domicilio')->dispatched_by_user_id);

        // Nunca se marca como salido un domicilio solo impreso, ni nada que no sea domicilio.
        foreach (['impreso_nuevo_flujo', 'entregado_domicilio', 'por_cobrar_llevar', 'entregado_mesa', 'preparacion_llevar', 'pendiente', 'cancelado'] as $key) {
            $this->assertNull($row($key)->dispatched_at, $key);
            $this->assertNull($row($key)->dispatched_by_user_id, $key);
        }

        // No se borra nada y el historial queda igual.
        $this->assertSame(count($ids), DB::table('orders')->count());
        $this->assertSame($historyBefore, DB::table('order_status_histories')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());

        // Volver a ejecutarla no cambia nada más.
        $snapshot = DB::table('orders')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->migration()->up();
        $this->assertSame($snapshot, DB::table('orders')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }

    public function test_new_dispatch_events_are_never_used_as_legacy_evidence(): void
    {
        $rider = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $id = $this->insertOrder('DOMICILIO', 'POR COBRAR', $rider->id);
        // Evento del flujo nuevo (nota distinta) sin marca: la migración no lo toma como evidencia.
        $this->history($id, 'POR COBRAR', 'POR COBRAR', $rider->id, '2026-10-06 09:00:00', '🛵 Domicilio salió.');

        $this->migration()->up();

        $this->assertNull(DB::table('orders')->find($id)->dispatched_at);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\TableSessionStatus;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Flujo de estados: PENDIENTE → POR COBRAR → TERMINADO (+ CANCELADO, ver OrderCancellationTest).
 * - Imprimir comandas: PENDIENTE → POR COBRAR en los tres tipos (registra quién imprimió).
 * - MESA: imprimir la cuenta no cambia el estado.
 * - DOMICILIO: "🛵 Salió" es una marca (dispatched_at), no un estado.
 * - Las adiciones devuelven el pedido a PENDIENTE hasta imprimirlas.
 * - Solo el ADMIN cobra, desde POR COBRAR (nunca PENDIENTE).
 * - ENTREGADO, EN PREPARACIÓN y EN CAMINO son heredados: se leen como POR COBRAR.
 */
class OrderStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $dish;

    private Product $granizado;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $platos = Category::create(['name' => 'Platos', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $granizadas = Category::create(['name' => 'Granizadas', 'description' => null, 'sort_order' => 2, 'is_active' => true]);
        $this->dish = Product::create(['category_id' => $platos->id, 'name' => 'Plato del día', 'description' => null, 'price' => 20000, 'is_available' => true]);
        $this->granizado = Product::create(['category_id' => $granizadas->id, 'name' => 'Granizada de Mora', 'description' => null, 'price' => 8000, 'is_available' => true]);
        $this->table = RestaurantTable::create(['number' => 3, 'capacity' => 4, 'qr_token' => 'mesa-3', 'status' => TableStatus::AVAILABLE]);
    }

    private function createOrder(OrderType $type, ?array $items = null): Order
    {
        $data = ['type' => $type->value, 'items' => $items ?? [$this->dish->id => 1]];
        if ($type === OrderType::TABLE) {
            $data['table_id'] = $this->table->id;
        }
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }

        $this->actingAs($this->admin)->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function print(Order $order, ?User $user = null): void
    {
        $this->actingAs($user ?? $this->admin)->get(route('admin.orders.print', $order))->assertOk();
    }

    private function printAccount(Order $order, ?User $user = null): void
    {
        $this->actingAs($user ?? $this->waiter)->get(route('admin.accounts.print', $order->table_session_id))->assertOk();
    }

    private function salio(Order $order, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->waiter)->put(route('admin.orders.dispatch', $order));
    }

    /** @return array<int, array{0: ?string, 1: string}> */
    private function history(Order $order): array
    {
        return $order->statusHistories()->orderBy('id')->get()
            ->map(fn ($h) => [$h->previous_status, $h->new_status])->all();
    }

    /** Última transición registrada: [estado anterior, estado nuevo]. */
    private function lastTransition(Order $order): array
    {
        $history = $this->history($order);

        return end($history) ?: [];
    }

    private function snapshot(): array
    {
        return collect(['orders', 'order_items', 'order_rounds', 'order_status_histories', 'table_sessions', 'restaurant_tables'])
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])
            ->all();
    }

    // --- Estado inicial ---

    public function test_pendiente_is_the_initial_state_for_every_order_type(): void
    {
        foreach ([OrderType::TABLE, OrderType::TAKEAWAY, OrderType::DELIVERY] as $type) {
            $order = $this->createOrder($type);
            $this->assertSame(OrderStatus::PENDING, $order->status, $type->value);
            $this->assertSame([[null, 'PENDIENTE']], $this->history($order));
        }

        $this->postJson('/api/orders', ['type' => 'MESA', 'table_token' => 'mesa-3', 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])
            ->assertCreated()->assertJsonPath('order.status', 'PENDIENTE');
    }

    // --- Imprimir: PENDIENTE → POR COBRAR ---

    public function test_printing_moves_pending_to_por_cobrar_in_every_type_without_entregado(): void
    {
        foreach ([OrderType::TABLE, OrderType::TAKEAWAY, OrderType::DELIVERY] as $type) {
            $order = $this->createOrder($type);
            $this->print($order);

            $order->refresh();
            $this->assertSame(OrderStatus::TO_COLLECT, $order->status, $type->value);
            $this->assertSame($this->admin->id, $order->delivered_by_user_id);
            $this->assertNotNull($order->delivered_at);
            $this->assertNull($order->dispatched_at);
            $this->assertSame(0, $order->orderItems()->whereNull('sent_at')->count(), 'sent_at se sigue marcando.');
            $this->assertSame([[null, 'PENDIENTE'], ['PENDIENTE', 'POR COBRAR']], $this->history($order), $type->value);
            $this->assertSame('Comandas impresas.', $order->statusHistories()->latest('id')->value('notes'));
        }

        $this->assertSame(0, DB::table('order_status_histories')->where('new_status', 'ENTREGADO')->count());
        $this->assertSame(0, Order::where('status', 'ENTREGADO')->count());
    }

    public function test_reprint_does_not_change_state_or_history(): void
    {
        foreach ([OrderType::TAKEAWAY, OrderType::DELIVERY] as $type) {
            $order = $this->createOrder($type);
            $this->print($order);
            if ($type === OrderType::DELIVERY) {
                $this->salio($order);
            }
            $before = $this->snapshot();

            $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk();
            $this->actingAs($this->admin)->get(route('admin.orders.reprint', $order))->assertOk();

            $this->assertSame($before, $this->snapshot(), $type->value);
        }
    }

    public function test_there_is_no_manual_preparing_ready_or_delivered_step_in_the_screens(): void
    {
        $this->createOrder(OrderType::TABLE);
        $this->print($takeaway = $this->createOrder(OrderType::TAKEAWAY));
        $this->print($delivery = $this->createOrder(OrderType::DELIVERY));

        foreach ([$this->waiter, $this->admin] as $user) {
            foreach ([route('waiter.orders'), route('admin.orders.show', $delivery), route('admin.orders.show', $takeaway), route('admin.orders.index'), route('admin.dashboard')] as $url) {
                if ($user === $this->waiter && $url !== route('waiter.orders')) {
                    continue;
                }
                $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
                foreach (['Marcar como listo', 'Marcar en camino', 'EN PREPARACIÓN', 'EN CAMINO', 'ENTREGADO', 'Entregados', 'LISTO</', 'orders/'.$takeaway->id.'/deliver'] as $old) {
                    $this->assertStringNotContainsString($old, $html, "{$url}: {$old}");
                }
            }
        }
    }

    // --- MESA ---

    public function test_table_flow_print_then_admin_pays_and_printing_the_account_does_not_change_states(): void
    {
        $first = $this->createOrder(OrderType::TABLE);
        $this->print($first);
        $this->assertSame(OrderStatus::TO_COLLECT, $first->fresh()->status);

        // Segundo pedido de la misma mesa todavía sin imprimir.
        $second = $this->createOrder(OrderType::TABLE);
        $this->assertSame($first->table_session_id, $second->table_session_id);

        // Imprimir la cuenta no cambia estados ni historial.
        $historyCount = DB::table('order_status_histories')->count();
        $this->printAccount($first);
        $this->assertSame(OrderStatus::TO_COLLECT, $first->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $second->fresh()->status);
        $this->assertSame($historyCount, DB::table('order_status_histories')->count());

        // Con un pedido PENDIENTE la cuenta no se puede cobrar.
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $first->table_session_id))->assertSessionHasErrors('status');

        $this->print($second);
        $this->assertSame(OrderStatus::TO_COLLECT, $second->fresh()->status);

        // El mesero no puede cobrar; el ADMIN sí, desde POR COBRAR.
        $this->actingAs($this->waiter)->post(route('admin.accounts.pay', $first->table_session_id))->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $first->table_session_id))->assertSessionHasNoErrors();

        foreach ([$first, $second] as $order) {
            $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
            $this->assertSame([[null, 'PENDIENTE'], ['PENDIENTE', 'POR COBRAR'], ['POR COBRAR', 'TERMINADO']], $this->history($order));
        }
        $this->assertSame(TableSessionStatus::CLOSED, TableSession::find($first->table_session_id)->status);
        $this->assertSame(TableStatus::AVAILABLE, $this->table->fresh()->status);
    }

    public function test_table_addition_goes_back_to_pending_and_printing_it_returns_to_por_cobrar(): void
    {
        $order = $this->createOrder(OrderType::TABLE);
        $this->print($order);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);

        // La mesa sigue abierta: se puede agregar aunque esté POR COBRAR.
        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), ['items' => [$this->dish->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(['POR COBRAR', 'PENDIENTE'], $this->lastTransition($order));

        // Caja ve que hay algo pendiente de impresión.
        $this->actingAs($this->admin)->getJson(route('admin.orders.pending'))->assertJsonPath('ids', [$order->id]);

        $this->print($order);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
        $this->assertSame(['PENDIENTE', 'POR COBRAR'], $this->lastTransition($order));
        $this->assertSame('Nueva adición impresa.', $order->statusHistories()->latest('id')->value('notes'));
    }

    // --- PARA_LLEVAR ---

    public function test_takeaway_flow_print_moves_to_por_cobrar_then_admin_pays(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);

        // PENDIENTE no se puede cobrar.
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasErrors('status');

        $this->print($order);
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::TO_COLLECT, $fresh->status);
        $this->assertSame($this->admin->id, $fresh->delivered_by_user_id);
        $this->assertSame([[null, 'PENDIENTE'], ['PENDIENTE', 'POR COBRAR']], $this->history($order));

        // Disponible para cobro: el ADMIN ve el botón; el mesero ve el mensaje de caja.
        $this->actingAs($this->admin)->get(route('waiter.orders'))->assertSee(route('admin.orders.pay', $order), false)->assertSee('💰 Registrar pago')->assertSee('POR COBRAR</span>', false);
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertDontSee(route('admin.orders.pay', $order), false)->assertSee('Pendiente de cobro en caja.');

        $this->actingAs($this->waiter)->post(route('admin.orders.pay', $order))->assertForbidden();
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertSame([[null, 'PENDIENTE'], ['PENDIENTE', 'POR COBRAR'], ['POR COBRAR', 'TERMINADO']], $this->history($order));
    }

    public function test_takeaway_reprint_does_not_change_anything(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);
        $this->print($order);
        $before = $this->snapshot();

        $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.orders.reprint', $order))->assertOk();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
    }

    public function test_takeaway_addition_while_por_cobrar_goes_back_to_pending_and_printing_returns_to_por_cobrar(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->granizado->id => 1]);
        $this->print($order);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);

        // Se sigue pudiendo agregar a un PARA_LLEVAR ya impreso (botón visible y adición aceptada).
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertSee(route('admin.orders.edit', $order), false);
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertSee(route('admin.orders.edit', $order), false);
        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), ['items' => [$this->granizado->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);

        // No se puede cobrar mientras la adición está PENDIENTE.
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasErrors('status');

        $this->print($order);
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::TO_COLLECT, $fresh->status);
        $this->assertSame(['PENDIENTE', 'POR COBRAR'], $this->lastTransition($order));
        // Icopor y totales intactos: 2 granizados ($16.000) + 2 × $1.500.
        $this->assertSame(3000, (int) $fresh->packaging_fee);
        $this->assertSame(16000 + 3000, (int) $fresh->total);
    }

    // --- DOMICILIO ---

    public function test_delivery_flow_salio_is_a_mark_and_does_not_change_the_state(): void
    {
        $order = $this->createOrder(OrderType::DELIVERY, [$this->granizado->id => 2]);

        // "Salió" exige que el domicilio ya esté impreso (POR COBRAR).
        $this->salio($order)->assertSessionHasErrors('status');
        $this->assertNull($order->fresh()->dispatched_at);

        $this->print($order);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertSee('🛵 Salió');

        $this->salio($order)->assertRedirect(route('waiter.orders'));
        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::TO_COLLECT, $fresh->status, '"Salió" no cambia el estado.');
        $this->assertNotNull($fresh->dispatched_at);
        $this->assertSame($this->waiter->id, $fresh->dispatched_by_user_id);
        $this->assertSame($this->waiter->id, $fresh->delivered_by_user_id, 'Entregas por usuario: cuenta quien marcó "Salió".');
        $this->assertSame(['POR COBRAR', 'POR COBRAR'], $this->lastTransition($order));
        $this->assertSame('🛵 Domicilio salió.', $order->statusHistories()->latest('id')->value('notes'));

        // La marca se ve en el mesero y en el detalle.
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertSee('🛵 Salió '.$fresh->dispatched_at->format('H:i'))->assertDontSee(route('admin.orders.dispatch', $order), false);
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertSee('🛵 Salió el')->assertSee('POR COBRAR');

        // Ya salió: no se puede volver a marcar ni agregar productos.
        $this->salio($order)->assertSessionHasErrors('status');
        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), ['items' => [$this->dish->id => 1]])->assertSessionHasErrors('order');
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertDontSee(route('admin.orders.edit', $order), false);

        $this->actingAs($this->waiter)->post(route('admin.orders.pay', $order))->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::COMPLETED, $fresh->status);
        $this->assertSame([[null, 'PENDIENTE'], ['PENDIENTE', 'POR COBRAR'], ['POR COBRAR', 'POR COBRAR'], ['POR COBRAR', 'TERMINADO']], $this->history($order));
        // Icopor y totales sin cambios: 2 granizados ($16.000) + 2 × $1.500 + domicilio $3.000.
        $this->assertSame(3000, (int) $fresh->packaging_fee);
        $this->assertSame(16000 + 3000 + 3000, (int) $fresh->total);
    }

    public function test_admin_can_pay_a_delivery_without_salio_and_salio_only_applies_to_deliveries(): void
    {
        $delivery = $this->createOrder(OrderType::DELIVERY);
        $this->print($delivery);
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $delivery))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::COMPLETED, $delivery->fresh()->status);

        $takeaway = $this->createOrder(OrderType::TAKEAWAY);
        $this->print($takeaway);
        $before = $this->snapshot();
        $this->salio($takeaway)->assertSessionHasErrors('status');
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(OrderStatus::TO_COLLECT, $takeaway->fresh()->status);
    }

    public function test_delivery_addition_before_salio_returns_to_pending_and_printing_returns_to_por_cobrar(): void
    {
        $order = $this->createOrder(OrderType::DELIVERY);
        $this->print($order);

        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), ['items' => [$this->dish->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);

        // Mientras la adición esté PENDIENTE no puede salir.
        $this->salio($order)->assertSessionHasErrors('status');

        $this->print($order);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
        $this->salio($order)->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
    }

    // --- Estados heredados ---

    public function test_legacy_states_are_read_as_por_cobrar_and_can_still_be_collected(): void
    {
        $delivered = Order::create(['type' => OrderType::TAKEAWAY, 'status' => OrderStatus::DELIVERED, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);
        $preparing = Order::create(['type' => OrderType::TAKEAWAY, 'status' => OrderStatus::PREPARING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);
        $inTransit = Order::create(['type' => OrderType::DELIVERY, 'status' => OrderStatus::IN_TRANSIT, 'subtotal' => 1000, 'delivery_fee' => 0, 'tax' => 0, 'total' => 1000, 'customer_name' => 'C', 'customer_phone' => '3', 'delivery_address' => 'D']);
        $session = TableSession::create(['restaurant_table_id' => $this->table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);
        $tableLegacy = Order::create(['table_session_id' => $session->id, 'type' => OrderType::TABLE, 'status' => OrderStatus::DELIVERED, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);

        foreach ([$delivered, $preparing, $inTransit, $tableLegacy] as $order) {
            $this->assertSame(OrderStatus::TO_COLLECT, $order->status->operational());
            // La lectura del pedido no se rompe.
            $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('POR COBRAR');
        }
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertOk();
        $this->actingAs($this->waiter)->get(route('admin.accounts.show', $session))->assertOk()->assertSee('POR COBRAR');

        $this->actingAs($this->admin)->post(route('admin.orders.pay', $delivered))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $preparing))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $inTransit))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $session))->assertSessionHasNoErrors();

        foreach ([$delivered, $preparing, $inTransit, $tableLegacy] as $order) {
            $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
        }
        $this->assertSame(['EN PREPARACIÓN', 'TERMINADO'], $this->lastTransition($preparing));
        $this->assertSame(['EN CAMINO', 'TERMINADO'], $this->lastTransition($inTransit));
    }

    public function test_legacy_orders_are_listed_under_por_cobrar(): void
    {
        Order::create(['type' => OrderType::TAKEAWAY, 'status' => OrderStatus::PREPARING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);
        Order::create(['type' => OrderType::DELIVERY, 'status' => OrderStatus::IN_TRANSIT, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);
        Order::create(['type' => OrderType::TAKEAWAY, 'status' => OrderStatus::DELIVERED, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);

        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'POR COBRAR']))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->count() === 3);
        $this->actingAs($this->admin)->get(route('admin.orders.index'))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->count() === 3);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertViewHas('toCollectOrders', 3)->assertViewMissing('deliveredOrders');
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertOk()->assertViewHas('counts', fn ($c) => $c['to_collect'] === 3 && ! isset($c['delivered']));
        // Los estados heredados no se ofrecen como opción.
        $this->assertSame([OrderStatus::PENDING, OrderStatus::TO_COLLECT, OrderStatus::COMPLETED, OrderStatus::CANCELLED], OrderStatus::operationalCases());
    }

    // --- Reportes y cierre del día ---

    public function test_daily_report_and_close_day_keep_working_with_the_new_states(): void
    {
        $table = $this->createOrder(OrderType::TABLE);
        $this->print($table);
        $this->printAccount($table);
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $table->table_session_id))->assertSessionHasNoErrors();

        $delivery = $this->createOrder(OrderType::DELIVERY);
        $this->print($delivery);
        $this->salio($delivery);

        // Un domicilio POR COBRAR bloquea el cierre del día.
        $this->actingAs($this->admin)->from(route('admin.reports.daily'))->post(route('admin.reports.daily.close'))->assertSessionHasErrors('close');

        $this->actingAs($this->admin)->post(route('admin.orders.pay', $delivery))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('admin.reports.daily'))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['orders'] === 2 && (int) $s['revenue'] === 20000 + 23000)
            // Entregas por usuario: la mesa la entregó quien imprimió (ADMIN) y el domicilio quien marcó "Salió" (mesero).
            ->assertViewHas('deliveryStats', fn ($stats) => $stats->pluck('delivered_count', 'name')->all() === [$this->admin->name => 1, $this->waiter->name => 1]
                || $stats->pluck('delivered_count', 'name')->all() === [$this->waiter->name => 1, $this->admin->name => 1]);

        $this->actingAs($this->admin)->post(route('admin.reports.daily.close'))->assertOk()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 0);
    }

    // --- Permisos de cobro intactos ---

    public function test_waiter_can_view_and_print_account_but_never_pay(): void
    {
        $order = $this->createOrder(OrderType::TABLE);
        $this->print($order);
        $takeaway = $this->createOrder(OrderType::TAKEAWAY);
        $this->print($takeaway);

        $this->actingAs($this->waiter)->get(route('admin.accounts.show', $order->table_session_id))->assertOk()->assertSee('🧾 Imprimir cuenta')->assertDontSee('Registrar pago y terminar');
        $this->printAccount($order, $this->waiter);
        $before = $this->snapshot();

        $this->actingAs($this->waiter)->post(route('admin.accounts.pay', $order->table_session_id))->assertForbidden();
        $this->actingAs($this->waiter)->post(route('admin.orders.pay', $takeaway))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_api_delivery_order_starts_pending(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/orders', ['type' => 'DOMICILIO', 'customer_name' => 'C', 'customer_phone' => '3', 'delivery_address' => 'D', 'delivery_fee' => '0', 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])
            ->assertCreated()->assertJsonPath('order.status', 'PENDIENTE');
    }
}

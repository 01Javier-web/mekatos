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
use Tests\TestCase;

/**
 * CANCELADO: excepción del flujo. ADMIN y MESERO cancelan pedidos PENDIENTES o POR COBRAR con
 * un motivo obligatorio. El pedido se conserva, pero queda fuera de ventas, cobros, pendientes,
 * pedidos activos y cierre del día.
 */
class OrderCancellationTest extends TestCase
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
        $this->table = RestaurantTable::create(['number' => 7, 'capacity' => 4, 'qr_token' => 'mesa-7', 'status' => TableStatus::AVAILABLE]);
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

    private function print(Order $order): void
    {
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();
    }

    private function cancel(Order $order, ?string $reason = 'El cliente desistió', ?User $user = null)
    {
        return $this->actingAs($user ?? $this->waiter)->put(route('admin.orders.cancel', $order), ['reason' => $reason]);
    }

    private function orderSnapshot(Order $order): array
    {
        return [
            DB::table('order_items')->where('order_id', $order->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            (array) DB::table('orders')->where('id', $order->id)->first(['subtotal', 'packaging_fee', 'delivery_fee', 'total', 'paid_at']),
        ];
    }

    public function test_waiter_cancels_a_pending_order_with_reason_and_nothing_is_deleted(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->granizado->id => 2]);
        $before = $this->orderSnapshot($order);

        $this->cancel($order, '  El cliente desistió del pedido  ')->assertRedirect(route('waiter.orders'))->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::CANCELLED, $fresh->status);
        $this->assertSame($before, $this->orderSnapshot($order), 'Productos, totales e icopor se conservan.');
        $last = $fresh->statusHistories()->latest('id')->first();
        $this->assertSame(['PENDIENTE', 'CANCELADO'], [$last->previous_status, $last->new_status]);
        $this->assertSame('Motivo de la cancelación: El cliente desistió del pedido', $last->notes);
        $this->assertSame($this->waiter->id, $last->changed_by_user_id);

        // El motivo se ve en el detalle.
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('CANCELADO')->assertSee('Motivo de la cancelación: El cliente desistió del pedido')
            ->assertDontSee(route('admin.orders.cancel', $order), false)->assertDontSee(route('admin.orders.edit', $order), false);
    }

    public function test_admin_cancels_an_order_por_cobrar(): void
    {
        $order = $this->createOrder(OrderType::DELIVERY);
        $this->print($order);
        $this->actingAs($this->admin)->put(route('admin.orders.dispatch', $order))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);

        $this->cancel($order, 'Dirección inexistente', $this->admin)->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(['POR COBRAR', 'CANCELADO'], [$order->statusHistories()->latest('id')->value('previous_status'), $order->statusHistories()->latest('id')->value('new_status')]);
        $this->assertNotNull($order->fresh()->dispatched_at, 'La marca de salida se conserva.');
    }

    public function test_terminado_and_cancelado_cannot_be_cancelled(): void
    {
        $paid = $this->createOrder(OrderType::TAKEAWAY);
        $this->print($paid);
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $paid))->assertSessionHasNoErrors();
        $historyCount = $paid->statusHistories()->count();

        $this->cancel($paid, 'Error', $this->admin)->assertSessionHasErrors('status');
        $this->assertSame(OrderStatus::COMPLETED, $paid->fresh()->status);
        $this->assertSame($historyCount, $paid->statusHistories()->count());
        $this->actingAs($this->admin)->get(route('admin.orders.show', $paid))->assertDontSee(route('admin.orders.cancel', $paid), false);

        $cancelled = $this->createOrder(OrderType::TAKEAWAY);
        $this->cancel($cancelled)->assertSessionHasNoErrors();
        $this->cancel($cancelled, 'Otra vez')->assertSessionHasErrors('status');
        $this->assertSame(1, $cancelled->statusHistories()->where('new_status', 'CANCELADO')->count());
    }

    public function test_reason_is_required(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);

        foreach ([null, '', '    '] as $reason) {
            $this->cancel($order, $reason)->assertSessionHasErrors('reason');
        }
        $this->cancel($order, str_repeat('x', 501))->assertSessionHasErrors('reason');

        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(0, $order->statusHistories()->where('new_status', 'CANCELADO')->count());
    }

    public function test_cancelled_orders_are_out_of_pending_active_lists_payments_print_and_additions(): void
    {
        $pending = $this->createOrder(OrderType::TAKEAWAY);
        $toCollect = $this->createOrder(OrderType::DELIVERY);
        $this->print($toCollect);
        $this->cancel($pending)->assertSessionHasNoErrors();
        $this->cancel($toCollect)->assertSessionHasNoErrors();

        // Pendientes de impresión (badge de caja) y pantalla del mesero.
        $this->actingAs($this->admin)->getJson(route('admin.orders.pending'))->assertJsonPath('count', 0);
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->isEmpty())
            ->assertViewHas('counts', fn ($c) => $c['total'] === 0 && $c['pending'] === 0 && $c['to_collect'] === 0);
        // Listado de caja: fuera por defecto, visible con el filtro CANCELADO.
        $this->actingAs($this->admin)->get(route('admin.orders.index'))->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->actingAs($this->admin)->get(route('admin.orders.index', ['status' => 'CANCELADO']))->assertViewHas('orders', fn ($orders) => $orders->count() === 2);
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertViewHas('pendingOrders', 0)->assertViewHas('toCollectOrders', 0);

        // No se cobra, no se imprime y no admite adiciones.
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $toCollect))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->get(route('admin.orders.print', $pending))->assertSessionHasErrors('status');
        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $pending), ['items' => [$this->dish->id => 1]])->assertSessionHasErrors('order');
        $this->assertSame(OrderStatus::CANCELLED, $toCollect->fresh()->status);
        $this->assertNull($toCollect->fresh()->paid_at);
    }

    public function test_cancelled_orders_are_not_sales_and_do_not_block_close_day(): void
    {
        $sold = $this->createOrder(OrderType::TAKEAWAY);
        $this->print($sold);
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $sold))->assertSessionHasNoErrors();

        $cancelled = $this->createOrder(OrderType::TAKEAWAY, [$this->dish->id => 5]);
        $this->print($cancelled);
        $this->cancel($cancelled)->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->get(route('admin.reports.daily'))->assertOk()
            ->assertViewHas('summary', fn ($s) => $s['orders'] === 1 && (int) $s['revenue'] === 20000 && (int) $s['items'] === 1);

        $this->actingAs($this->admin)->post(route('admin.reports.daily.close'))->assertOk()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_table_account_excludes_cancelled_orders_and_can_be_paid(): void
    {
        $kept = $this->createOrder(OrderType::TABLE);
        $this->print($kept);
        // Un pedido PENDIENTE bloquea el cobro de la mesa; al cancelarlo, la cuenta se puede cobrar.
        $mistake = $this->createOrder(OrderType::TABLE, [$this->granizado->id => 3]);
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $kept->table_session_id))->assertSessionHasErrors('status');

        $this->cancel($mistake)->assertSessionHasNoErrors();
        $session = TableSession::find($kept->table_session_id);
        $this->assertSame(TableSessionStatus::Active, $session->status, 'La mesa sigue abierta: queda un pedido activo.');
        $this->assertSame(TableStatus::OCCUPIED, $this->table->fresh()->status);

        $this->actingAs($this->waiter)->get(route('admin.accounts.show', $session))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$kept->id])
            ->assertViewHas('total', fn ($total) => (int) $total === 20000);
        $this->actingAs($this->waiter)->get(route('admin.accounts.print', $session))->assertOk()->assertDontSee('#'.$mistake->id);

        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $session))->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::COMPLETED, $kept->fresh()->status);
        $this->assertSame(OrderStatus::CANCELLED, $mistake->fresh()->status);
        $this->assertNull($mistake->fresh()->paid_at);
        $this->assertSame(TableSessionStatus::CLOSED, $session->fresh()->status);
        $this->assertSame(TableStatus::AVAILABLE, $this->table->fresh()->status);
    }

    public function test_cancelling_the_last_active_order_of_a_table_releases_it(): void
    {
        $first = $this->createOrder(OrderType::TABLE);
        $second = $this->createOrder(OrderType::TABLE);
        $this->print($first);
        $session = TableSession::find($first->table_session_id);

        $this->cancel($first)->assertSessionHasNoErrors();
        $this->assertSame(TableSessionStatus::Active, $session->fresh()->status);

        $this->cancel($second)->assertSessionHasNoErrors();
        $fresh = $session->fresh();
        $this->assertSame(TableSessionStatus::CLOSED, $fresh->status);
        $this->assertNotNull($fresh->ended_at);
        $this->assertSame(TableStatus::AVAILABLE, $this->table->fresh()->status);

        // La mesa vuelve a recibir pedidos en una sesión nueva.
        $next = $this->createOrder(OrderType::TABLE);
        $this->assertNotSame($session->id, $next->table_session_id);
        $this->assertSame(OrderStatus::PENDING, $next->status);
    }

    public function test_cancel_form_is_shown_for_active_orders_to_admin_and_waiter(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);

        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertSee(route('admin.orders.cancel', $order), false)->assertSee('Motivo de la cancelación');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertSee(route('admin.orders.cancel', $order), false)->assertSee('❌ Cancelar pedido');
        // Sin sesión no se puede cancelar.
        auth()->logout();
        $this->put(route('admin.orders.cancel', $order), ['reason' => 'x'])->assertRedirect(route('login'));
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }
}

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
 * El MESERO ve e imprime la cuenta (con el total) para cobrar físicamente en la mesa,
 * pero registrar el pago (cerrar la cuenta de mesa, pagos de PARA_LLEVAR y DOMICILIO)
 * es solo de caja/ADMIN. El ADMIN conserva todos sus permisos.
 */
class WaiterPaymentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const SNAPSHOT_TABLES = ['orders', 'order_items', 'order_rounds', 'order_status_histories', 'table_sessions', 'restaurant_tables'];

    private User $admin;

    private User $waiter;

    private Product $dish;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $category = Category::create(['name' => 'Platos', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $this->dish = Product::create(['category_id' => $category->id, 'name' => 'Plato del día', 'description' => null, 'price' => 20000, 'is_available' => true]);
        $this->table = RestaurantTable::create(['number' => 7, 'capacity' => 4, 'qr_token' => 'mesa-7', 'status' => TableStatus::AVAILABLE]);
    }

    private function createOrder(OrderType $type): Order
    {
        $data = ['type' => $type->value, 'items' => [$this->dish->id => 1]];
        if ($type === OrderType::TABLE) {
            $data['table_id'] = $this->table->id;
        }
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }

        $this->actingAs($this->admin)->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        return $order->fresh();
    }

    /** Dos pedidos de mesa enviados a cocina: cuenta acumulada de $40.000. */
    private function tableAccount(): TableSession
    {
        $first = $this->createOrder(OrderType::TABLE);
        $this->createOrder(OrderType::TABLE);

        return TableSession::findOrFail($first->table_session_id);
    }

    /** PARA_LLEVAR impreso (POR COBRAR), disponible para cobro. */
    private function readyTakeaway(): Order
    {
        return $this->createOrder(OrderType::TAKEAWAY);
    }

    /** DOMICILIO que "🛵 Salió" (POR COBRAR), pendiente de registrar el pago. */
    private function deliveryInTransit(): Order
    {
        $order = $this->createOrder(OrderType::DELIVERY);
        $this->actingAs($this->admin)->put(route('admin.orders.dispatch', $order))->assertSessionHasNoErrors();

        return $order->fresh();
    }

    private function snapshot(): array
    {
        return collect(self::SNAPSHOT_TABLES)
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])
            ->all();
    }

    // --- MESERO: ver e imprimir la cuenta completa ---

    public function test_waiter_can_view_and_print_the_full_account_with_total(): void
    {
        $session = $this->tableAccount();

        $this->actingAs($this->waiter)->get(route('admin.accounts.show', $session))
            ->assertOk()
            ->assertSee('$40.000')
            ->assertSee('🧾 Imprimir cuenta')
            ->assertSee(route('admin.accounts.print', $session), false)
            ->assertDontSee('Registrar pago y terminar')
            ->assertDontSee(route('admin.accounts.pay', $session), false);

        $this->actingAs($this->waiter)->get(route('admin.accounts.print', $session))
            ->assertOk()
            ->assertSee('MESA 7')
            ->assertSee('$40.000');
    }

    // --- MESERO: no puede registrar pagos (403 y sin cambios) ---

    public function test_waiter_gets_403_when_trying_to_pay_and_close_a_table(): void
    {
        $session = $this->tableAccount();
        $before = $this->snapshot();

        $this->actingAs($this->waiter)->post(route('admin.accounts.pay', $session))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(TableSessionStatus::Active, $session->fresh()->status);
        $this->assertSame(TableStatus::OCCUPIED, $this->table->fresh()->status);
        $this->assertSame(0, Order::where('status', OrderStatus::COMPLETED->value)->count());
    }

    public function test_waiter_gets_403_when_trying_to_register_takeaway_and_delivery_payments(): void
    {
        $takeaway = $this->readyTakeaway();
        $delivery = $this->deliveryInTransit();
        $before = $this->snapshot();

        $this->actingAs($this->waiter)->post(route('admin.orders.pay', $takeaway))->assertForbidden();
        $this->actingAs($this->waiter)->post(route('admin.orders.pay', $delivery))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(OrderStatus::TO_COLLECT, $takeaway->fresh()->status);
        $this->assertSame(OrderStatus::TO_COLLECT, $delivery->fresh()->status);
        $this->assertNull($takeaway->fresh()->paid_at);
        $this->assertNull($delivery->fresh()->paid_at);
    }

    // --- MESERO: su pantalla ---

    public function test_waiter_screen_shows_view_account_and_pending_payment_message_without_payment_buttons(): void
    {
        $session = $this->tableAccount();
        $takeaway = $this->readyTakeaway();
        $delivery = $this->deliveryInTransit();

        $html = $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertOk()->getContent();

        $this->assertStringContainsString('🧾 Ver cuenta', $html);
        $this->assertStringContainsString(route('admin.accounts.show', $session), $html);
        $this->assertStringNotContainsString('Ver / cobrar cuenta', $html);
        $this->assertSame(2, substr_count($html, 'Pendiente de cobro en caja.'), 'Uno para el PARA_LLEVAR y otro para el DOMICILIO.');
        $this->assertStringNotContainsString(route('admin.orders.pay', $takeaway), $html);
        $this->assertStringNotContainsString(route('admin.orders.pay', $delivery), $html);
        $this->assertStringNotContainsString('Registrar pago', $html);
        $this->assertStringNotContainsString('Confirmar entrega y pago', $html);
        // Conserva la reimpresión (f9ab765).
        $this->assertStringContainsString(route('admin.orders.reprint', $takeaway), $html);
    }

    // --- MESERO: conserva sus demás funciones ---

    public function test_waiter_keeps_reprint_and_operational_status_actions_but_not_the_operational_print(): void
    {
        $takeaway = $this->createOrder(OrderType::TAKEAWAY);
        $delivery = $this->createOrder(OrderType::DELIVERY);

        $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $takeaway))->assertOk()->assertSee('REIMPRESIÓN');
        $this->actingAs($this->waiter)->get(route('admin.orders.print', $takeaway))->assertForbidden();

        // El mesero conserva "🛵 Salió" en los domicilios (antes "Marcar en camino").
        $this->actingAs($this->waiter)->put(route('admin.orders.dispatch', $delivery))->assertRedirect(route('waiter.orders'));

        $this->assertSame(OrderStatus::TO_COLLECT, $takeaway->fresh()->status);
        $this->assertSame(OrderStatus::TO_COLLECT, $delivery->fresh()->status);
        $this->assertSame($this->waiter->id, $delivery->fresh()->delivered_by_user_id);
    }

    // --- ADMIN: conserva todo ---

    public function test_admin_sees_payment_actions_on_account_and_waiter_screen(): void
    {
        $session = $this->tableAccount();
        $takeaway = $this->readyTakeaway();
        $delivery = $this->deliveryInTransit();

        $this->actingAs($this->admin)->get(route('admin.accounts.show', $session))
            ->assertOk()
            ->assertSee('🧾 Imprimir cuenta')
            ->assertSee('💰 Registrar pago y terminar')
            ->assertSee(route('admin.accounts.pay', $session), false);

        $html = $this->actingAs($this->admin)->get(route('waiter.orders'))->assertOk()->getContent();
        $this->assertStringContainsString('💰 Ver / cobrar cuenta', $html);
        $this->assertStringContainsString('💰 Registrar pago', $html);
        $this->assertStringContainsString('💰 Confirmar entrega y pago', $html);
        $this->assertStringContainsString(route('admin.orders.pay', $takeaway), $html);
        $this->assertStringContainsString(route('admin.orders.pay', $delivery), $html);
        $this->assertStringNotContainsString('Pendiente de cobro en caja.', $html);
        $this->assertStringNotContainsString('🧾 Ver cuenta', $html);
    }

    public function test_admin_can_still_pay_tables_takeaway_and_delivery_and_print(): void
    {
        $session = $this->tableAccount();
        $takeaway = $this->readyTakeaway();
        $delivery = $this->deliveryInTransit();

        $this->actingAs($this->admin)->get(route('admin.accounts.print', $session))->assertOk()->assertSee('$40.000');
        $this->actingAs($this->admin)->get(route('admin.orders.reprint', $takeaway))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $session))->assertRedirect(route('admin.orders.index'))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $takeaway))->assertRedirect(route('admin.orders.index'))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $delivery))->assertRedirect(route('admin.orders.index'))->assertSessionHasNoErrors();

        $this->assertSame(TableSessionStatus::CLOSED, $session->fresh()->status);
        $this->assertSame(TableStatus::AVAILABLE, $this->table->fresh()->status);
        $this->assertSame(4, Order::where('status', OrderStatus::COMPLETED->value)->where('paid_by_user_id', $this->admin->id)->count());
    }
}

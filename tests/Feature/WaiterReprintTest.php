<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El MESERO puede reimprimir (solo lectura) la última comanda enviada, igual que
 * el ADMIN. La impresión operativa (que envía a cocina y cambia el estado) sigue
 * siendo solo del ADMIN.
 */
class WaiterReprintTest extends TestCase
{
    use RefreshDatabase;

    private const SNAPSHOT_TABLES = ['orders', 'order_items', 'order_rounds', 'order_status_histories', 'table_sessions', 'restaurant_tables'];

    private User $admin;

    private User $waiter;

    private Product $dish;

    private Product $portion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $category = Category::create(['name' => 'Platos', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $this->dish = Product::create(['category_id' => $category->id, 'name' => 'Plato del día', 'description' => null, 'price' => 20000, 'is_available' => true]);
        $this->portion = Product::create(['category_id' => $category->id, 'name' => 'Porción de papas', 'description' => null, 'price' => 6000, 'is_available' => true, 'is_portion' => true]);
    }

    private function createOrder(OrderType $type, array $extra = []): Order
    {
        $this->actingAs($this->admin)
            ->post(route('admin.orders.store'), array_merge(['type' => $type->value, 'items' => [$this->dish->id => 1]], $extra))
            ->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    /** Pedido de mesa con porción, ya enviado a cocina por el ADMIN (EN PREPARACIÓN). */
    private function sentTableOrder(): Order
    {
        $table = RestaurantTable::create(['number' => 1, 'capacity' => 4, 'qr_token' => 'mesa-1', 'status' => TableStatus::AVAILABLE]);
        $order = $this->createOrder(OrderType::TABLE, [
            'table_id' => $table->id,
            'items' => [$this->dish->id => 1, $this->portion->id => 1],
            'portion_pairing' => [$this->portion->id => $this->dish->id],
        ]);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        return $order->fresh();
    }

    private function snapshot(): array
    {
        return collect(self::SNAPSHOT_TABLES)
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])
            ->all();
    }

    // --- ADMIN conserva sus permisos ---

    public function test_admin_can_still_reprint_without_changing_anything(): void
    {
        $order = $this->sentTableOrder();
        $before = $this->snapshot();

        $this->actingAs($this->admin)->get(route('admin.orders.reprint', $order))
            ->assertOk()
            ->assertSee('REIMPRESIÓN');

        $this->assertSame($before, $this->snapshot());
    }

    public function test_admin_can_still_use_the_operational_print(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);

        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
    }

    // --- MESERO puede reimprimir, solo lectura ---

    public function test_waiter_can_reprint_a_table_order_with_portion_without_changing_anything(): void
    {
        $order = $this->sentTableOrder();
        $this->assertNotNull(DB::table('order_items')->whereNotNull('paired_order_item_id')->first(), 'El pedido incluye una porción emparejada.');
        $before = $this->snapshot();

        $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))
            ->assertOk()
            ->assertSee('REIMPRESIÓN')
            ->assertSee('Plato del día');

        // Estado, sent_at, rondas, historial, pagos, sesión, mesa y porciones: idénticos.
        $this->assertSame($before, $this->snapshot());
    }

    public function test_waiter_can_reprint_takeaway_and_delivery_orders_without_changing_anything(): void
    {
        $takeaway = $this->createOrder(OrderType::TAKEAWAY);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $takeaway))->assertOk();

        $delivery = $this->createOrder(OrderType::DELIVERY, ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000']);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $delivery))->assertOk();
        $this->actingAs($this->admin)->put(route('admin.orders.dispatch', $delivery));

        $this->assertSame(OrderStatus::TO_COLLECT, $takeaway->fresh()->status);
        $this->assertSame(OrderStatus::TO_COLLECT, $delivery->fresh()->status);
        $before = $this->snapshot();

        foreach ([$takeaway, $delivery, $takeaway] as $order) {
            $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk()->assertSee('REIMPRESIÓN');
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_waiter_reprint_of_an_order_never_sent_is_rejected_and_changes_nothing(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);
        $before = $this->snapshot();

        $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertSessionHasErrors('status');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    // --- MESERO no puede usar la impresión operativa ---

    public function test_waiter_cannot_use_the_operational_print(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);
        $before = $this->snapshot();

        $this->actingAs($this->waiter)->get(route('admin.orders.print', $order))->assertForbidden();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertSame(0, $order->orderItems()->whereNotNull('sent_at')->count());
    }

    // --- Botón en la pantalla del mesero ---

    public function test_waiter_screen_shows_reprint_only_for_orders_with_a_sent_comanda(): void
    {
        $sent = $this->sentTableOrder();
        $pending = $this->createOrder(OrderType::TAKEAWAY);

        $response = $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertOk();

        $response->assertSee(route('admin.orders.reprint', $sent), false)
            ->assertDontSee(route('admin.orders.reprint', $pending), false)
            ->assertDontSee(route('admin.orders.print', $sent), false)
            ->assertDontSee(route('admin.orders.print', $pending), false)
            ->assertSee('Reimprimir última comanda');
    }

    public function test_waiter_screen_has_no_reprint_button_when_nothing_was_sent(): void
    {
        $this->createOrder(OrderType::TAKEAWAY);

        $this->actingAs($this->waiter)->get(route('waiter.orders'))
            ->assertOk()
            ->assertDontSee('Reimprimir última comanda')
            ->assertSee('Esperando impresión en caja.');
    }
}

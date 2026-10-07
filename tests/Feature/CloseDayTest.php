<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\BeverageOption;
use App\Models\Category;
use App\Models\JuiceFruit;
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
 * Cierre del día: reinicia la operación desde cero (regla de negocio de Mekatos),
 * pero solo cuando todos los pedidos están finalizados.
 */
class CloseDayTest extends TestCase
{
    use RefreshDatabase;

    private const OPERATIONAL_TABLES = ['orders', 'order_items', 'order_rounds', 'order_status_histories', 'table_sessions'];

    private User $admin;

    private Product $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $category = Category::create(['name' => 'Platos', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $this->dish = Product::create(['category_id' => $category->id, 'name' => 'Plato del día', 'description' => null, 'price' => 20000, 'is_available' => true]);
    }

    private function table(int $number): RestaurantTable
    {
        return RestaurantTable::create(['number' => $number, 'capacity' => 4, 'qr_token' => "mesa-{$number}", 'status' => TableStatus::AVAILABLE]);
    }

    // --- Flujos reales para llevar los pedidos a cada estado ---

    private function createOrder(OrderType $type, ?RestaurantTable $table = null): Order
    {
        $data = ['type' => $type->value, 'items' => [$this->dish->id => 2]];

        if ($type === OrderType::TABLE) {
            $data['table_id'] = $table->id;
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

    /** "🛵 Salió": marca del domicilio (sigue POR COBRAR). */
    private function dispatchDelivery(Order $order): void
    {
        $this->actingAs($this->admin)->put(route('admin.orders.dispatch', $order))->assertSessionHasNoErrors();
    }

    private function payOrder(Order $order): void
    {
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasNoErrors();
    }

    private function payTable(Order $order): void
    {
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))->assertSessionHasNoErrors();
    }

    /** Un pedido de cada tipo, todos cobrados (TERMINADO). */
    private function completedDayWithEveryOrderType(): array
    {
        $table = $this->table(1);

        $tableOrder = $this->createOrder(OrderType::TABLE, $table);
        $this->print($tableOrder);
        $this->payTable($tableOrder);

        $takeaway = $this->createOrder(OrderType::TAKEAWAY);
        $this->print($takeaway);
        $this->payOrder($takeaway);

        $delivery = $this->createOrder(OrderType::DELIVERY);
        $this->print($delivery);
        $this->dispatchDelivery($delivery);
        $this->payOrder($delivery);

        foreach ([$tableOrder, $takeaway, $delivery] as $order) {
            $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
        }

        return [$table, $tableOrder, $takeaway, $delivery];
    }

    private function closeDay()
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.reports.daily'))
            ->post(route('admin.reports.daily.close'));
    }

    private function operationalCounts(): array
    {
        return collect(self::OPERATIONAL_TABLES)->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();
    }

    private function assertOperationalDataIsEmpty(): void
    {
        foreach (self::OPERATIONAL_TABLES as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    private function assertRejectedWithoutChanges(array $before, Order ...$activeOrders): void
    {
        $response = $this->closeDay()
            ->assertRedirect(route('admin.reports.daily'))
            ->assertSessionHasErrors('close');

        $message = session('errors')->first('close');
        $this->assertStringContainsString('No se puede cerrar el día', $message);
        $this->assertStringContainsString('Finaliza primero esos pedidos', $message);
        foreach ($activeOrders as $order) {
            $this->assertStringContainsString('#'.$order->id, $message);
        }

        $this->assertSame($before, $this->operationalCounts(), 'Un cierre rechazado no debe borrar nada.');
    }

    // --- Cierres exitosos ---

    public function test_close_succeeds_when_every_order_type_is_completed_and_resets_everything(): void
    {
        [$table, $tableOrder, $takeaway, $delivery] = $this->completedDayWithEveryOrderType();
        $cleaning = RestaurantTable::create(['number' => 2, 'capacity' => 2, 'qr_token' => 'mesa-2', 'status' => TableStatus::CLEANING]);
        $occupied = RestaurantTable::create(['number' => 3, 'capacity' => 2, 'qr_token' => 'mesa-3', 'status' => TableStatus::OCCUPIED]);

        // Hay datos de todas las tablas operativas antes de cerrar.
        foreach ($this->operationalCounts() as $tableName => $count) {
            $this->assertGreaterThan(0, $count, "{$tableName} debería tener datos antes del cierre.");
        }

        $expectedRevenue = (int) Order::sum('total');

        $this->closeDay()
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->assertViewHas('closed', true)
            ->assertViewHas('summary', fn (array $summary) => (int) $summary['revenue'] === $expectedRevenue && $summary['orders'] === 3);

        $this->assertOperationalDataIsEmpty();

        foreach ([$table, $cleaning, $occupied] as $restaurantTable) {
            $this->assertSame(TableStatus::AVAILABLE, $restaurantTable->fresh()->status);
        }
    }

    public function test_after_a_successful_close_the_system_starts_a_new_day(): void
    {
        $this->completedDayWithEveryOrderType();

        $this->closeDay()->assertOk();

        // Ventas del día en cero.
        $this->actingAs($this->admin)->get(route('admin.reports.daily'))
            ->assertOk()
            ->assertViewHas('closed', false)
            ->assertViewHas('summary', fn (array $summary) => (float) $summary['revenue'] === 0.0 && $summary['orders'] === 0);

        // La numeración vuelve a empezar y la mesa se puede volver a usar.
        $next = $this->createOrder(OrderType::TABLE, RestaurantTable::firstOrFail());
        $this->assertSame(1, $next->id);
        $this->assertSame(TableStatus::OCCUPIED, RestaurantTable::firstOrFail()->status);
    }

    public function test_close_succeeds_with_cancelled_orders(): void
    {
        $this->completedDayWithEveryOrderType();
        Order::create(['table_session_id' => null, 'type' => OrderType::TAKEAWAY, 'status' => OrderStatus::CANCELLED, 'subtotal' => 5000, 'tax' => 0, 'total' => 5000]);

        $this->closeDay()->assertOk()->assertSessionHasNoErrors();

        $this->assertOperationalDataIsEmpty();
    }

    public function test_close_with_no_orders_at_all_still_releases_tables(): void
    {
        $table = RestaurantTable::create(['number' => 9, 'capacity' => 2, 'qr_token' => 'mesa-9', 'status' => TableStatus::OCCUPIED]);

        $this->closeDay()->assertOk()->assertSessionHasNoErrors();

        $this->assertSame(TableStatus::AVAILABLE, $table->fresh()->status);
    }

    public function test_close_keeps_catalog_users_and_configuration(): void
    {
        $soda = Product::create(['category_id' => $this->dish->category_id, 'name' => 'Gaseosa', 'description' => null, 'price' => 4000, 'is_available' => true]);
        BeverageOption::create(['product_id' => $soda->id, 'name' => 'Coca-Cola', 'sort_order' => 1, 'is_available' => true]);
        JuiceFruit::query()->firstOrCreate(['name' => 'Maracuyá'], ['sort_order' => 1, 'is_available' => true]);
        User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $this->completedDayWithEveryOrderType();

        $permanent = ['products', 'categories', 'users', 'restaurant_tables', 'beverage_options', 'juice_fruits'];
        $before = collect($permanent)->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();

        $this->closeDay()->assertOk();

        $after = collect($permanent)->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();
        $this->assertSame($before, $after);
        $this->assertModelExists($this->dish);
        $this->assertModelExists($this->admin);
    }

    // --- Cierres rechazados: nada se borra ---

    public function test_close_is_rejected_with_a_pending_order(): void
    {
        $this->completedDayWithEveryOrderType();
        $pending = $this->createOrder(OrderType::TABLE, $this->table(5));
        $this->assertSame(OrderStatus::PENDING, $pending->fresh()->status);

        $this->assertRejectedWithoutChanges($this->operationalCounts(), $pending);

        $this->assertSame(TableStatus::OCCUPIED, RestaurantTable::where('number', 5)->first()->status);
    }

    public function test_close_is_rejected_with_a_printed_but_unpaid_table_order(): void
    {
        $this->completedDayWithEveryOrderType();
        $printed = $this->createOrder(OrderType::TABLE, $this->table(5));
        $this->print($printed);
        $this->assertSame(OrderStatus::TO_COLLECT, $printed->fresh()->status);

        $this->assertRejectedWithoutChanges($this->operationalCounts(), $printed);

        $this->assertSame(OrderStatus::TO_COLLECT, $printed->fresh()->status);
        $this->assertSame(TableStatus::OCCUPIED, RestaurantTable::where('number', 5)->first()->status);
    }

    public function test_close_is_rejected_with_a_printed_but_unpaid_takeaway(): void
    {
        $this->completedDayWithEveryOrderType();
        $ready = $this->createOrder(OrderType::TAKEAWAY);
        $this->print($ready);
        $this->assertSame(OrderStatus::TO_COLLECT, $ready->fresh()->status);

        $this->assertRejectedWithoutChanges($this->operationalCounts(), $ready);

        $this->assertSame(OrderStatus::TO_COLLECT, $ready->fresh()->status);
    }

    public function test_close_is_rejected_with_orders_to_collect(): void
    {
        $this->completedDayWithEveryOrderType();
        $delivery = $this->createOrder(OrderType::DELIVERY);
        $this->print($delivery);
        $this->dispatchDelivery($delivery);
        $tableOrder = $this->createOrder(OrderType::TABLE, $this->table(6));
        $this->print($tableOrder);
        $this->actingAs($this->admin)->get(route('admin.accounts.print', $tableOrder->table_session_id))->assertOk();
        $this->assertSame(OrderStatus::TO_COLLECT, $delivery->fresh()->status);
        $this->assertSame(OrderStatus::TO_COLLECT, $tableOrder->fresh()->status);

        $this->assertRejectedWithoutChanges($this->operationalCounts(), $delivery, $tableOrder);

        $this->assertSame(OrderStatus::TO_COLLECT, $delivery->fresh()->status);
        $this->assertSame(OrderStatus::TO_COLLECT, $tableOrder->fresh()->status);
    }

    public function test_close_is_rejected_with_legacy_preparing_and_in_transit_orders(): void
    {
        $this->completedDayWithEveryOrderType();
        $preparing = Order::create(['table_session_id' => null, 'type' => OrderType::TAKEAWAY, 'status' => OrderStatus::PREPARING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);
        $inTransit = Order::create(['table_session_id' => null, 'type' => OrderType::DELIVERY, 'status' => OrderStatus::IN_TRANSIT, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);

        $this->assertRejectedWithoutChanges($this->operationalCounts(), $preparing, $inTransit);

        $this->assertSame(OrderStatus::PREPARING, $preparing->fresh()->status);
        $this->assertSame(OrderStatus::IN_TRANSIT, $inTransit->fresh()->status);
    }

    public function test_close_is_rejected_by_unknown_or_empty_statuses(): void
    {
        // Valores fuera del flujo actual (por ejemplo, el antiguo valor por
        // defecto 'PENDING') no se pueden dar por terminados: se bloquea el cierre.
        $this->completedDayWithEveryOrderType();

        foreach (['PENDING', 'PREPARANDO', ''] as $status) {
            DB::table('orders')->insert([
                'table_session_id' => null, 'type' => OrderType::TAKEAWAY->value, 'status' => $status,
                'subtotal' => 1000, 'tax' => 0, 'total' => 1000, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $before = $this->operationalCounts();
        $legacy = Order::query()->whereIn('status', ['PENDING', 'PREPARANDO', ''])->get();

        $this->assertRejectedWithoutChanges($before, ...$legacy->all());
        $this->assertStringContainsString('sin estado', session('errors')->first('close'));
    }

    public function test_rejected_close_keeps_every_order_type_and_its_data(): void
    {
        $table = $this->table(5);
        $tableOrder = $this->createOrder(OrderType::TABLE, $table);
        $takeaway = $this->createOrder(OrderType::TAKEAWAY);
        $delivery = $this->createOrder(OrderType::DELIVERY);
        $this->print($delivery);

        $before = $this->operationalCounts();
        $this->assertRejectedWithoutChanges($before, $tableOrder, $takeaway, $delivery);

        $this->assertSame(OrderStatus::PENDING, $tableOrder->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $takeaway->fresh()->status);
        $this->assertSame(OrderStatus::TO_COLLECT, $delivery->fresh()->status);
        $this->assertSame(TableStatus::OCCUPIED, $table->fresh()->status);
        $this->assertSame(2, $tableOrder->fresh()->orderItems()->sum('quantity'));
    }
}

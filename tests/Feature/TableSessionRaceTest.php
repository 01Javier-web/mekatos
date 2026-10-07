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
use App\Support\TableSessionLock;
use App\TableSessionStatus;
use App\TableStatus;
use App\UserRole;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Carrera entre crear/modificar un pedido de mesa y cobrar la cuenta de esa mesa.
 *
 * SQLite (usado en las pruebas) no tiene bloqueos de fila ni dos conexiones
 * simultáneas, así que la concurrencia real de MySQL no se puede reproducir aquí.
 * En su lugar:
 *  - Se simula la ventana de carrera: justo cuando la operación abre su transacción,
 *    un listener aplica los efectos de un cobro de mesa ya confirmado. Es lo que
 *    vería la operación si el cobro terminara entre su comprobación previa y su
 *    escritura. La operación debe volver a comprobar con las filas bloqueadas.
 *  - Se comprueba el orden de las lecturas de bloqueo (mesa → sesión → pedidos).
 */
class TableSessionRaceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $category = Category::create(['name' => 'Platos', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $this->dish = Product::create(['category_id' => $category->id, 'name' => 'Plato del día', 'description' => null, 'price' => 20000, 'is_available' => true]);
    }

    private function table(int $number = 1): RestaurantTable
    {
        return RestaurantTable::create(['number' => $number, 'capacity' => 4, 'qr_token' => "qr-mesa-{$number}", 'status' => TableStatus::AVAILABLE]);
    }

    private function webTableOrder(RestaurantTable $table): Order
    {
        $this->actingAs($this->admin)
            ->post(route('admin.orders.store'), ['type' => OrderType::TABLE->value, 'table_id' => $table->id, 'items' => [$this->dish->id => 1]])
            ->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function printOrder(Order $order): void
    {
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();
    }

    /**
     * Simula que el cobro de la mesa termina justo cuando la siguiente operación
     * abre su transacción (después de cualquier comprobación previa).
     */
    private function paySessionWhenNextTransactionBegins(TableSession $session): void
    {
        $done = false;

        Event::listen(TransactionBeginning::class, function () use (&$done, $session): void {
            if ($done) {
                return;
            }
            $done = true;

            DB::table('orders')->where('table_session_id', $session->id)
                ->update(['status' => OrderStatus::COMPLETED->value, 'paid_at' => now(), 'paid_by_user_id' => $this->admin->id]);
            DB::table('table_sessions')->where('id', $session->id)
                ->update(['status' => TableSessionStatus::CLOSED->value, 'ended_at' => now()]);
            DB::table('restaurant_tables')->where('id', $session->restaurant_table_id)
                ->update(['status' => TableStatus::AVAILABLE->value]);
        });
    }

    private function counts(): array
    {
        return collect(['orders', 'order_items', 'order_rounds', 'order_status_histories', 'table_sessions'])
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])
            ->all();
    }

    /**
     * Tablas que se leen con "select *" dentro de la transacción de la operación,
     * en orden de aparición. Las lecturas previas (route model binding) no cuentan.
     * SQLite no escribe FOR UPDATE, pero esas lecturas son las que bloquean en MySQL.
     */
    private function selectOrder(callable $operation): array
    {
        $tables = [];
        $inTransaction = false;
        Event::listen(TransactionBeginning::class, function () use (&$inTransaction): void {
            $inTransaction = true;
        });
        DB::listen(function ($query) use (&$tables, &$inTransaction): void {
            if ($inTransaction && preg_match('/^select \* from "(restaurant_tables|table_sessions|orders)"/', $query->sql, $m)) {
                $tables[] = $m[1];
            }
        });

        $operation();

        return array_values(array_unique($tables));
    }

    // --- Flujo normal (sin cambios) ---

    public function test_normal_table_order_and_payment_still_work(): void
    {
        $table = $this->table();
        $first = $this->webTableOrder($table);
        $second = $this->webTableOrder($table);

        $this->assertSame($first->table_session_id, $second->table_session_id, 'Los pedidos de la misma mesa comparten la cuenta.');
        $this->assertSame(TableStatus::OCCUPIED, $table->fresh()->status);

        $this->printOrder($first);
        $this->printOrder($second);

        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $first->table_session_id))
            ->assertRedirect(route('admin.orders.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(TableSessionStatus::CLOSED, TableSession::find($first->table_session_id)->status);
        $this->assertSame(OrderStatus::COMPLETED, $first->fresh()->status);
        $this->assertSame(OrderStatus::COMPLETED, $second->fresh()->status);
        $this->assertSame(TableStatus::AVAILABLE, $table->fresh()->status);

        // Tras cobrar, un nuevo pedido abre una cuenta nueva.
        $next = $this->webTableOrder($table);
        $this->assertNotSame($first->table_session_id, $next->table_session_id);
    }

    public function test_normal_payment_rules_are_unchanged(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);

        // Sin imprimir no se puede cobrar (regla existente).
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))->assertSessionHasErrors('status');
        $this->assertSame(TableSessionStatus::Active, TableSession::find($order->table_session_id)->status);

        $this->printOrder($order);
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))->assertSessionHasNoErrors();

        // Una cuenta ya cerrada no se vuelve a cobrar (regla existente).
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))
            ->assertSessionHasErrors(['table' => 'La cuenta de esta mesa ya está cerrada.']);
    }

    public function test_qr_order_and_addition_on_an_active_session_still_work(): void
    {
        $table = $this->table();

        $this->postJson('/api/orders', ['type' => 'MESA', 'table_token' => $table->qr_token, 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])->assertCreated();
        $order = Order::query()->firstOrFail();

        $this->postJson('/api/orders', ['type' => 'MESA', 'table_session_id' => $order->table_session_id, 'table_token' => $table->qr_token, 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])
            ->assertCreated()
            ->assertJsonPath('order.table_session_id', $order->table_session_id);

        $this->actingAs($this->admin)->post(route('admin.orders.add.store', $order), ['items' => [$this->dish->id => 2]])->assertSessionHasNoErrors();
        $this->assertSame(2, $order->orderItems()->count());
    }

    public function test_takeaway_and_delivery_orders_are_unchanged(): void
    {
        $this->actingAs($this->admin)->post(route('admin.orders.store'), ['type' => 'PARA_LLEVAR', 'items' => [$this->dish->id => 1]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.orders.store'), [
            'type' => 'DOMICILIO', 'items' => [$this->dish->id => 1],
            'customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000',
        ])->assertSessionHasNoErrors();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/orders', ['type' => 'PARA_LLEVAR', 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])->assertCreated();

        $this->assertSame(0, TableSession::count());
        $this->assertSame(2, Order::whereNull('table_session_id')->where('type', 'PARA_LLEVAR')->count());
        $this->assertDatabaseHas('orders', ['type' => 'DOMICILIO', 'table_session_id' => null, 'total' => 23000]);
    }

    // --- Sesión ya cerrada ---

    public function test_api_order_for_an_already_closed_session_is_rejected_without_partial_data(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);
        $this->printOrder($order);
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id));
        $before = $this->counts();

        $this->postJson('/api/orders', ['type' => 'MESA', 'table_session_id' => $order->table_session_id, 'table_token' => $table->qr_token, 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.table_session_id.0', TableSessionLock::SESSION_CLOSED);

        $this->assertSame($before, $this->counts());
        $this->assertSame(1, Order::where('table_session_id', $order->table_session_id)->count());
        $this->assertSame(TableStatus::AVAILABLE, $table->fresh()->status);
    }

    // --- Ventana de carrera simulada ---

    public function test_api_order_racing_with_the_payment_is_rejected_and_leaves_nothing(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);
        $session = TableSession::findOrFail($order->table_session_id);
        $before = $this->counts();

        $this->paySessionWhenNextTransactionBegins($session);

        $this->postJson('/api/orders', ['type' => 'MESA', 'table_session_id' => $session->id, 'table_token' => $table->qr_token, 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.table_session_id.0', TableSessionLock::SESSION_CLOSED);

        // La sesión cerrada no recibió el pedido y no quedó nada a medias.
        // (El cobro simulado corre dentro de la transacción rechazada y también se
        // deshace; el estado de la mesa tras un rechazo se comprueba en el caso
        // secuencial test_api_order_for_an_already_closed_session_…)
        $this->assertSame($before, $this->counts());
        $this->assertSame([$order->id], Order::where('table_session_id', $session->id)->pluck('id')->all());
    }

    public function test_addition_racing_with_the_payment_is_rejected_and_leaves_nothing(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);
        $this->printOrder($order);
        $session = TableSession::findOrFail($order->table_session_id);
        $before = $this->counts();

        $this->paySessionWhenNextTransactionBegins($session);

        $this->actingAs($this->admin)
            ->from(route('admin.orders.edit', $order))
            ->post(route('admin.orders.add.store', $order), ['items' => [$this->dish->id => 3]])
            ->assertSessionHasErrors(['order' => TableSessionLock::SESSION_CLOSED]);

        // Sin items, rondas ni historial nuevos (el cobro simulado se deshace con la
        // transacción rechazada; el caso secuencial está en el test siguiente).
        $this->assertSame($before, $this->counts());
        $this->assertSame(1, $order->orderItems()->count());
    }

    public function test_addition_to_an_already_paid_table_is_rejected_and_does_not_reopen_it(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);
        $this->printOrder($order);
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))->assertSessionHasNoErrors();
        $before = $this->counts();

        $this->actingAs($this->admin)
            ->post(route('admin.orders.add.store', $order), ['items' => [$this->dish->id => 3]])
            ->assertSessionHasErrors('order');

        $this->assertSame($before, $this->counts());
        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status, 'El pedido cobrado no se reabre.');
        $this->assertSame(TableStatus::AVAILABLE, $table->fresh()->status);
    }

    public function test_web_table_order_racing_with_the_payment_opens_a_new_account(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);
        $session = TableSession::findOrFail($order->table_session_id);

        $this->paySessionWhenNextTransactionBegins($session);
        $next = $this->webTableOrder($table);

        $this->assertNotSame($session->id, $next->table_session_id);
        $this->assertSame([$order->id], Order::where('table_session_id', $session->id)->pluck('id')->all());
        $this->assertSame(TableSessionStatus::Active, TableSession::find($next->table_session_id)->status);
        $this->assertSame(TableStatus::OCCUPIED, $table->fresh()->status);
    }

    public function test_qr_order_by_token_racing_with_the_payment_opens_a_new_account(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);
        $session = TableSession::findOrFail($order->table_session_id);

        $this->paySessionWhenNextTransactionBegins($session);

        $this->postJson('/api/orders', ['type' => 'MESA', 'table_token' => $table->qr_token, 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])
            ->assertCreated();

        $next = Order::query()->latest('id')->firstOrFail();
        $this->assertNotSame($session->id, $next->table_session_id);
        $this->assertSame([$order->id], Order::where('table_session_id', $session->id)->pluck('id')->all());
    }

    // --- Orden de bloqueo (mesa → sesión → pedidos) ---

    public function test_operations_lock_table_then_session_then_orders(): void
    {
        $table = $this->table();
        $order = $this->webTableOrder($table);
        $this->printOrder($order);

        $addition = $this->selectOrder(fn () => $this->actingAs($this->admin)
            ->post(route('admin.orders.add.store', $order), ['items' => [$this->dish->id => 1]])
            ->assertSessionHasNoErrors());
        $this->assertSame(['restaurant_tables', 'table_sessions', 'orders'], array_slice($addition, 0, 3), 'Adición');

        $this->printOrder($order);
        $payment = $this->selectOrder(fn () => $this->actingAs($this->admin)
            ->post(route('admin.accounts.pay', $order->table_session_id))
            ->assertSessionHasNoErrors());
        $this->assertSame(['restaurant_tables', 'table_sessions', 'orders'], array_slice($payment, 0, 3), 'Cobro');

        $qrOrder = $this->selectOrder(fn () => $this->postJson('/api/orders', ['type' => 'MESA', 'table_token' => $table->qr_token, 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])->assertCreated());
        $this->assertSame(['restaurant_tables', 'table_sessions'], array_slice($qrOrder, 0, 2), 'Pedido QR');

        $session = Order::query()->latest('id')->value('table_session_id');
        $qrWithSession = $this->selectOrder(fn () => $this->postJson('/api/orders', ['type' => 'MESA', 'table_session_id' => $session, 'table_token' => $table->qr_token, 'items' => [['product_id' => $this->dish->id, 'quantity' => 1]]])->assertCreated());
        $this->assertSame(['restaurant_tables', 'table_sessions'], array_slice($qrWithSession, 0, 2), 'Pedido QR con sesión');
    }
}

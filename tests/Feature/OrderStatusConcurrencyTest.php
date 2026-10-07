<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Web\Admin\OrderController;
use App\Http\Controllers\Web\PrintController;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Carreras entre cancelar, cobrar, imprimir y "🛵 Salió". Se simulan con un pedido leído ANTES
 * del cambio concurrente (como el que recibe una petición que llegó al mismo tiempo): cobrar,
 * imprimir y marcar la salida deben volver a comprobar el estado sobre la fila bloqueada.
 */
class OrderStatusConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $dish;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $category = Category::create(['name' => 'Platos', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $this->dish = Product::create(['category_id' => $category->id, 'name' => 'Plato del día', 'description' => null, 'price' => 20000, 'is_available' => true]);
    }

    private function createOrder(OrderType $type): Order
    {
        $data = ['type' => $type->value, 'items' => [$this->dish->id => 1]];
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('La acción debía rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_paying_with_a_stale_order_cannot_complete_a_cancelled_order(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $stale = Order::findOrFail($order->id); // leído POR COBRAR
        $this->actingAs($this->waiter)->put(route('admin.orders.cancel', $order), ['reason' => 'Desistió'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin);
        $this->assertRejected(fn () => app(PrintController::class)->payOrder($stale));

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::CANCELLED, $fresh->status);
        $this->assertNull($fresh->paid_at);
        $this->assertSame(0, $order->statusHistories()->where('new_status', 'TERMINADO')->count());
    }

    public function test_printing_with_a_stale_order_cannot_revive_a_cancelled_order(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);

        $stale = Order::findOrFail($order->id); // leído PENDIENTE
        $this->actingAs($this->waiter)->put(route('admin.orders.cancel', $order), ['reason' => 'Error de digitación'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin);
        $this->assertRejected(fn () => app(PrintController::class)->orderPack($stale));

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(0, $order->orderItems()->whereNotNull('sent_at')->count(), 'No se marcó nada como enviado.');
        $this->assertSame(0, $order->statusHistories()->where('new_status', 'POR COBRAR')->count());
    }

    public function test_printing_with_a_stale_order_cannot_print_twice(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);
        $stale = Order::findOrFail($order->id);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->assertRejected(fn () => app(PrintController::class)->orderPack($stale));
        $this->assertSame(1, $order->statusHistories()->where('new_status', 'POR COBRAR')->count());
    }

    public function test_salio_twice_with_a_stale_order_records_a_single_dispatch(): void
    {
        $order = $this->createOrder(OrderType::DELIVERY);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $stale = Order::findOrFail($order->id); // leído sin marca de salida
        $this->actingAs($this->waiter)->put(route('admin.orders.dispatch', $order))->assertSessionHasNoErrors();
        $first = $order->fresh();

        $this->actingAs($this->admin);
        $this->assertRejected(fn () => app(OrderController::class)->dispatch($stale));

        $fresh = $order->fresh();
        $this->assertSame($this->waiter->id, $fresh->dispatched_by_user_id, 'La primera salida no se sobrescribe.');
        $this->assertEquals($first->dispatched_at, $fresh->dispatched_at);
        $this->assertSame(1, $order->statusHistories()->where('notes', '🛵 Domicilio salió.')->count());
    }

    public function test_salio_with_a_stale_order_cannot_dispatch_a_cancelled_delivery(): void
    {
        $order = $this->createOrder(OrderType::DELIVERY);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $stale = Order::findOrFail($order->id);
        $this->actingAs($this->admin)->put(route('admin.orders.cancel', $order), ['reason' => 'Dirección errada'])->assertSessionHasNoErrors();

        $this->assertRejected(fn () => app(OrderController::class)->dispatch($stale));
        $this->assertNull($order->fresh()->dispatched_at);
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    public function test_double_payment_with_a_stale_order_is_rejected(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();
        $stale = Order::findOrFail($order->id); // leído POR COBRAR

        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasNoErrors();
        $paidAt = $order->fresh()->paid_at;

        $this->assertRejected(fn () => app(PrintController::class)->payOrder($stale));
        $this->assertEquals($paidAt, $order->fresh()->paid_at);
        $this->assertSame(1, $order->statusHistories()->where('new_status', 'TERMINADO')->count());
    }

    public function test_double_table_payment_is_rejected(): void
    {
        $table = RestaurantTable::create(['number' => 9, 'capacity' => 4, 'qr_token' => 'mesa-9', 'status' => TableStatus::AVAILABLE]);
        $this->actingAs($this->admin)->post(route('admin.orders.store'), ['type' => 'MESA', 'table_id' => $table->id, 'items' => [$this->dish->id => 1]])->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))->assertSessionHasErrors('table');
        $this->assertSame(1, $order->statusHistories()->where('new_status', 'TERMINADO')->count());
    }

    public function test_edit_built_before_printing_requires_a_reason_once_the_line_was_sent(): void
    {
        // Editar vs imprimir: la pantalla de edición se abrió antes de imprimir; al guardar,
        // la línea ya se envió y quitarla exige motivo (se comprueba sobre la fila bloqueada).
        $order = $this->createOrder(OrderType::TAKEAWAY);
        $line = $order->orderItems()->firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->actingAs($this->waiter)->post(route('admin.orders.update', $order), ['void' => [$line->id => 1], 'items' => [$this->dish->id => 1]])
            ->assertSessionHasErrors('reason');
        $this->assertNull($line->fresh()->voided_at);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
    }
}

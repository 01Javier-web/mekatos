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
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use RefreshDatabase;

    private function waiter(): User
    {
        return User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);
    }

    private function product(int $price = 15000): Product
    {
        $category = Category::create([
            'name' => 'Pruebas manuales',
            'description' => 'Categoría para pruebas',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Producto manual',
            'description' => 'Producto para pedidos manuales',
            'price' => $price,
            'is_available' => true,
        ]);
    }

    public function test_waiter_can_create_takeaway_order_from_web(): void
    {
        $waiter = $this->waiter();
        $product = $this->product(12000);

        $response = $this->actingAs($waiter)->post(route('admin.orders.store'), [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [$product->id => 2],
            'notes' => 'Sin salsa',
        ]);

        $order = Order::query()->latest('id')->first();

        $response->assertRedirect(route('waiter.orders'));
        $this->assertNotNull($order);
        $this->assertSame(OrderType::TAKEAWAY, $order->type);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(24000.0, (float) $order->total);
        $this->assertNull($order->table_session_id);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_waiter_can_create_table_order_and_open_active_session(): void
    {
        $waiter = $this->waiter();
        $product = $this->product();
        $table = RestaurantTable::create([
            'number' => 8,
            'name' => 'Mesa 8',
            'capacity' => 4,
            'qr_token' => 'manual-order-8',
            'status' => TableStatus::AVAILABLE,
        ]);

        $response = $this->actingAs($waiter)->post(route('admin.orders.store'), [
            'type' => OrderType::TABLE->value,
            'table_id' => $table->id,
            'items' => [$product->id => 1],
        ]);

        $order = Order::query()->latest('id')->first();

        $response->assertRedirect(route('waiter.orders'));
        $this->assertNotNull($order);
        $this->assertNotNull($order->table_session_id);
        $this->assertDatabaseHas('restaurant_tables', [
            'id' => $table->id,
            'status' => TableStatus::OCCUPIED->value,
        ]);
        $this->assertDatabaseHas('table_sessions', [
            'id' => $order->table_session_id,
            'restaurant_table_id' => $table->id,
            'status' => TableSessionStatus::Active->value,
        ]);
    }

    public function test_table_order_reuses_existing_active_session(): void
    {
        $waiter = $this->waiter();
        $product = $this->product();
        $table = RestaurantTable::create([
            'number' => 9,
            'capacity' => 4,
            'qr_token' => 'manual-order-9',
            'status' => TableStatus::OCCUPIED,
        ]);
        $session = TableSession::create([
            'restaurant_table_id' => $table->id,
            'status' => TableSessionStatus::Active,
            'started_at' => now(),
        ]);

        $this->actingAs($waiter)->post(route('admin.orders.store'), [
            'type' => OrderType::TABLE->value,
            'table_id' => $table->id,
            'items' => [$product->id => 1],
        ])->assertRedirect(route('waiter.orders'));

        $this->assertSame(1, TableSession::query()->where('restaurant_table_id', $table->id)->count());
        $this->assertSame($session->id, Order::query()->latest('id')->value('table_session_id'));
    }

    public function test_takeaway_cannot_receive_a_table(): void
    {
        $waiter = $this->waiter();
        $product = $this->product();
        $table = RestaurantTable::create([
            'number' => 10,
            'capacity' => 2,
            'qr_token' => 'manual-order-10',
            'status' => TableStatus::AVAILABLE,
        ]);

        $this->actingAs($waiter)->post(route('admin.orders.store'), [
            'type' => OrderType::TAKEAWAY->value,
            'table_id' => $table->id,
            'items' => [$product->id => 1],
        ])->assertSessionHasErrors('table_id');

        $this->assertDatabaseCount('orders', 0);
    }
}

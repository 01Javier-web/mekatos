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

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $price = 18000): Product
    {
        $category = Category::create([
            'name' => 'Pruebas',
            'description' => 'Categoría de pruebas',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Producto de prueba',
            'description' => 'Producto para pruebas automáticas',
            'price' => $price,
            'is_available' => true,
        ]);
    }

    private function waiter(): User
    {
        return User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);
    }

    public function test_table_order_calculates_total(): void
    {
        $product = $this->product();
        $table = RestaurantTable::create([
            'number' => 3,
            'name' => 'Mesa 3',
            'capacity' => 4,
            'qr_token' => 'order-test-3',
            'status' => TableStatus::OCCUPIED,
        ]);
        $session = TableSession::create([
            'restaurant_table_id' => $table->id,
            'status' => TableSessionStatus::Active,
            'started_at' => now(),
        ]);

        $response = $this->postJson('/api/orders', [
            'type' => OrderType::TABLE->value,
            'table_session_id' => $session->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.type', OrderType::TABLE->value)
            ->assertJsonPath('order.status', OrderStatus::PENDING->value)
            ->assertJsonPath('order.total', 36000);
    }

    public function test_takeaway_requires_authentication(): void
    {
        $product = $this->product();

        $this->postJson('/api/orders', [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnauthorized();
    }

    public function test_authenticated_takeaway_has_no_table_session(): void
    {
        $user = $this->waiter();
        $product = $this->product(12000);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/orders', [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('order.type', OrderType::TAKEAWAY->value)
            ->assertJsonPath('order.table_session_id', null)
            ->assertJsonPath('order.total', 12000);
    }

    public function test_unavailable_product_is_rejected(): void
    {
        $product = $this->product();
        $product->update(['is_available' => false]);
        $table = RestaurantTable::create([
            'number' => 4,
            'capacity' => 4,
            'qr_token' => 'order-test-4',
            'status' => TableStatus::OCCUPIED,
        ]);
        $session = TableSession::create([
            'restaurant_table_id' => $table->id,
            'status' => TableSessionStatus::Active,
            'started_at' => now(),
        ]);

        $this->postJson('/api/orders', [
            'type' => OrderType::TABLE->value,
            'table_session_id' => $session->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable();
    }

    public function test_ready_order_can_be_delivered_by_waiter(): void
    {
        $waiter = $this->waiter();
        $product = $this->product();
        $order = Order::create([
            'type' => OrderType::TAKEAWAY,
            'status' => OrderStatus::READY,
            'subtotal' => 18000,
            'tax' => 0,
            'total' => 18000,
        ]);
        $order->orderItems()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 18000,
            'total' => 18000,
        ]);

        $response = $this->actingAs($waiter, 'sanctum')
            ->putJson('/api/orders/' . $order->id . '/deliver');

        $response->assertOk()
            ->assertJsonPath('order.status', OrderStatus::DELIVERED->value);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
            'delivered_by_user_id' => $waiter->id,
        ]);
    }
}

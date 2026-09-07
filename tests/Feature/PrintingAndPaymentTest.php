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

class PrintingAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    }

    private function waiter(): User
    {
        return User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
    }

    private function product(string $name, string $categoryName, int $price = 10000): Product
    {
        $category = Category::firstOrCreate(['name' => $categoryName], ['description' => $categoryName, 'sort_order' => 1, 'is_active' => true]);
        return Product::create(['category_id' => $category->id, 'name' => $name, 'description' => $name, 'price' => $price, 'is_available' => true]);
    }

    private function order(OrderStatus $status, Product $product, ?TableSession $session = null): Order
    {
        $order = Order::create(['table_session_id' => $session?->id, 'type' => $session ? OrderType::TABLE : OrderType::TAKEAWAY, 'status' => $status, 'subtotal' => $product->price, 'tax' => 0, 'total' => $product->price]);
        $order->orderItems()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $product->price, 'total' => $product->price]);
        return $order;
    }

    public function test_printing_pending_order_moves_it_to_preparing_and_separates_juice_and_bottled_drinks(): void
    {
        $admin = $this->admin();
        $food = $this->product('Hamburguesa de prueba', 'Hamburguesas', 20000);
        $juice = $this->product('Jugo Natural Jarra', 'Jugos y Bebidas Preparadas', 8500);
        $soda = $this->product('Gaseosa 350 ml', 'Gaseosas y Agua', 4500);
        $table = RestaurantTable::create(['number' => 8, 'capacity' => 4, 'qr_token' => 'print-test-8', 'status' => TableStatus::OCCUPIED]);
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);
        $order = Order::create(['table_session_id' => $session->id, 'type' => OrderType::TABLE, 'status' => OrderStatus::PENDING, 'subtotal' => 33000, 'tax' => 0, 'total' => 33000, 'handled_by_user_id' => $admin->id]);
        foreach ([[$food, 1], [$juice, 1], [$soda, 1]] as [$product, $quantity]) {
            $order->orderItems()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => $product->price, 'total' => $product->price]);
        }

        $response = $this->actingAs($admin)->get(route('admin.orders.print', $order));
        $response->assertOk()->assertSee('Cocina')->assertSee('Jugos')->assertSee('Hamburguesa de prueba')->assertSee('Jugo Natural Jarra')->assertDontSee('Gaseosa 350 ml');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::PREPARING->value]);
    }

    public function test_table_account_accumulates_multiple_orders_and_payment_closes_session_and_frees_table(): void
    {
        $waiter = $this->waiter();
        $food = $this->product('Producto de cuenta', 'Hamburguesas', 20000);
        $table = RestaurantTable::create(['number' => 12, 'capacity' => 4, 'qr_token' => 'account-test-12', 'status' => TableStatus::OCCUPIED]);
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);
        $first = $this->order(OrderStatus::DELIVERED, $food, $session);
        $second = $this->order(OrderStatus::DELIVERED, $food, $session);

        $this->actingAs($waiter)->get(route('admin.accounts.show', $session))->assertOk()->assertSee('$40.000');
        $this->actingAs($waiter)->get(route('admin.accounts.print', $session))->assertOk()->assertSee('MESA 12')->assertSee('$40.000');
        $this->actingAs($waiter)->post(route('admin.accounts.pay', $session))->assertRedirect(route('waiter.orders'));

        $this->assertDatabaseHas('orders', ['id' => $first->id, 'status' => OrderStatus::COMPLETED->value, 'paid_by_user_id' => $waiter->id]);
        $this->assertDatabaseHas('orders', ['id' => $second->id, 'status' => OrderStatus::COMPLETED->value, 'paid_by_user_id' => $waiter->id]);
        $this->assertDatabaseHas('table_sessions', ['id' => $session->id, 'status' => TableSessionStatus::CLOSED->value]);
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'status' => TableStatus::AVAILABLE->value]);
    }

    public function test_table_account_cannot_be_paid_until_all_orders_are_delivered(): void
    {
        $waiter = $this->waiter();
        $food = $this->product('Producto pendiente', 'Hamburguesas', 20000);
        $table = RestaurantTable::create(['number' => 13, 'capacity' => 4, 'qr_token' => 'account-test-13', 'status' => TableStatus::OCCUPIED]);
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);
        $this->order(OrderStatus::PREPARING, $food, $session);

        $this->actingAs($waiter)->post(route('admin.accounts.pay', $session))->assertSessionHasErrors('status');
        $this->assertDatabaseHas('table_sessions', ['id' => $session->id, 'status' => TableSessionStatus::Active->value]);
    }
}

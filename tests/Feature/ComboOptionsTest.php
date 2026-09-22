<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\BeverageOption;
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

class ComboOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function hamburger(): Product
    {
        $category = Category::create([
            'name' => 'Hamburguesas',
            'description' => 'Hamburguesas',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Hamburguesa de Prueba',
            'description' => 'Hamburguesa',
            'price' => 27500,
            'is_available' => true,
        ]);
    }

    private function gaseosa(): Product
    {
        $category = Category::create([
            'name' => 'Gaseosas y Agua',
            'description' => 'Bebidas',
            'sort_order' => 17,
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Gaseosa 350 ml',
            'description' => null,
            'price' => 4500,
            'is_available' => true,
        ]);

        BeverageOption::create([
            'product_id' => $product->id,
            'name' => 'Coca-Cola',
            'sort_order' => 1,
            'is_available' => true,
        ]);

        return $product;
    }

    private function tableSession(): TableSession
    {
        $table = RestaurantTable::create([
            'number' => 1,
            'capacity' => 4,
            'qr_token' => 'combo-test-token',
            'status' => TableStatus::OCCUPIED,
        ]);

        return TableSession::create([
            'restaurant_table_id' => $table->id,
            'status' => TableSessionStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function test_api_adds_ten_thousand_for_hamburger_combo_and_saves_selection(): void
    {
        $hamburger = $this->hamburger();
        $this->gaseosa();
        $session = $this->tableSession();

        $response = $this->postJson('/api/orders', [
            'table_session_id' => $session->id,
            'items' => [[
                'product_id' => $hamburger->id,
                'quantity' => 1,
                'combo' => 'SI',
                'combo_beverage_type' => 'GASEOSA',
                'combo_beverage_flavor' => 'Coca-Cola',
            ]],
        ]);

        $response->assertCreated();
        $order = Order::latest('id')->first();

        $this->assertSame(37500, (int) $order->total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'unit_price' => 37500,
            'notes' => 'COMBO +$10.000 · Papas a la francesa · Gaseosa personal · Coca-Cola',
        ]);
    }

    public function test_api_without_combo_keeps_hamburger_price(): void
    {
        $hamburger = $this->hamburger();
        $session = $this->tableSession();

        $response = $this->postJson('/api/orders', [
            'table_session_id' => $session->id,
            'items' => [[
                'product_id' => $hamburger->id,
                'quantity' => 1,
                'combo' => 'NO',
            ]],
        ]);

        $response->assertCreated();
        $order = Order::latest('id')->first();

        $this->assertSame(27500, (int) $order->total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'unit_price' => 27500,
            'notes' => null,
        ]);
    }

    public function test_manual_order_adds_combo_price_and_selection(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $hamburger = $this->hamburger();
        $this->gaseosa();

        $table = RestaurantTable::create([
            'number' => 2,
            'capacity' => 4,
            'qr_token' => 'combo-manual-token',
            'status' => TableStatus::AVAILABLE,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TABLE->value,
            'table_id' => $table->id,
            'items' => [$hamburger->id => 1],
            'combo' => [$hamburger->id => 'SI'],
            'combo_beverage_type' => [$hamburger->id => 'GASEOSA'],
            'combo_beverage_flavor' => [$hamburger->id => 'Coca-Cola'],
        ]);

        $response->assertRedirect();
        $order = Order::latest('id')->first();

        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(37500, (int) $order->total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'unit_price' => 37500,
            'notes' => 'COMBO +$10.000 · Papas a la francesa · Gaseosa personal · Coca-Cola',
        ]);
    }
}

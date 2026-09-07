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

class JuiceOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function juice(): Product
    {
        $category = Category::create([
            'name' => 'Jugos y Bebidas Preparadas',
            'description' => 'Jugos',
            'sort_order' => 16,
            'is_active' => true,
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name' => 'Jugo Natural Jarra',
            'description' => 'En agua $8.500 o en leche $9.500.',
            'price' => 8500,
            'is_available' => true,
        ]);
    }

    private function session(): TableSession
    {
        $table = RestaurantTable::create([
            'number' => 1,
            'capacity' => 4,
            'qr_token' => 'juice-test-token',
            'status' => TableStatus::OCCUPIED,
        ]);

        return TableSession::create([
            'restaurant_table_id' => $table->id,
            'status' => TableSessionStatus::Active,
            'started_at' => now(),
        ]);
    }

    public function test_api_creates_natural_juice_in_water_with_selected_fruit(): void
    {
        $juice = $this->juice();
        $session = $this->session();

        $response = $this->postJson('/api/orders', [
            'table_session_id' => $session->id,
            'items' => [[
                'product_id' => $juice->id,
                'quantity' => 1,
                'juice_preparation' => 'AGUA',
                'juice_fruit' => 'MARACUYA',
            ]],
        ]);

        $response->assertCreated();
        $order = Order::latest('id')->first();
        $this->assertSame(8500, (int) $order->total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'unit_price' => 8500,
            'notes' => 'En agua · Maracuyá',
        ]);
    }

    public function test_api_prices_natural_juice_in_milk_at_nine_thousand_five_hundred(): void
    {
        $juice = $this->juice();
        $session = $this->session();

        $response = $this->postJson('/api/orders', [
            'table_session_id' => $session->id,
            'items' => [[
                'product_id' => $juice->id,
                'quantity' => 2,
                'juice_preparation' => 'LECHE',
                'juice_fruit' => 'FRESA',
            ]],
        ]);

        $response->assertCreated();
        $order = Order::latest('id')->first();
        $this->assertSame(19000, (int) $order->total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'quantity' => 2,
            'unit_price' => 9500,
            'total' => 19000,
            'notes' => 'En leche · Fresa',
        ]);
    }

    public function test_api_requires_details_when_juice_fruit_is_other(): void
    {
        $juice = $this->juice();
        $session = $this->session();

        $response = $this->postJson('/api/orders', [
            'table_session_id' => $session->id,
            'items' => [[
                'product_id' => $juice->id,
                'quantity' => 1,
                'juice_preparation' => 'AGUA',
                'juice_fruit' => 'OTRO',
            ]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('items');
    }

    public function test_manual_order_uses_the_selected_juice_preparation_price(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $juice = $this->juice();
        $table = RestaurantTable::create([
            'number' => 2,
            'capacity' => 4,
            'qr_token' => 'juice-manual-token',
            'status' => TableStatus::AVAILABLE,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TABLE->value,
            'table_id' => $table->id,
            'items' => [$juice->id => 1],
            'juice_preparation' => [$juice->id => 'LECHE'],
            'juice_fruit' => [$juice->id => 'LULO'],
            'item_notes' => [$juice->id => 'Sin azúcar'],
        ]);

        $response->assertRedirect();
        $order = Order::latest('id')->first();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(9500, (int) $order->total);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'unit_price' => 9500,
            'notes' => 'En leche · Lulo · Sin azúcar',
        ]);
    }
}

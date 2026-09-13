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

class OrderNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_keeps_general_order_note_and_individual_product_note_separately(): void
    {
        $user = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $category = Category::create(['name' => 'Prueba', 'description' => 'Prueba', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Hamburguesa de prueba', 'description' => 'Prueba', 'price' => 10000, 'is_available' => true]);
        $table = RestaurantTable::create(['number' => 1, 'capacity' => 4, 'qr_token' => 'notes-test-token', 'status' => TableStatus::OCCUPIED]);
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);

        $response = $this->actingAs($user)->postJson('/api/orders', [
            'type' => OrderType::TABLE->value,
            'table_session_id' => $session->id,
            'notes' => 'Nota general del pedido',
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 1,
                'notes' => 'Sin cebolla, agregar queso',
            ]],
        ]);

        $response->assertCreated();
        $order = Order::latest('id')->first();

        $this->assertSame('Nota general del pedido', $order->notes);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'notes' => 'Sin cebolla, agregar queso',
        ]);
        $this->assertSame(OrderStatus::PENDING, $order->status);
    }
}

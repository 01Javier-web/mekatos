<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function waiter(): User
    {
        return User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);
    }

    private function order(OrderStatus $status = OrderStatus::PENDING): Order
    {
        return Order::create([
            'type' => OrderType::TAKEAWAY,
            'status' => $status,
            'subtotal' => 10000,
            'tax' => 0,
            'total' => 10000,
        ]);
    }

    public function test_waiter_can_advance_order_through_valid_statuses(): void
    {
        $waiter = $this->waiter();
        $order = $this->order();

        $this->actingAs($waiter)->put(route('admin.orders.status', $order), [
            'status' => OrderStatus::PREPARING->value,
        ])->assertRedirect(route('waiter.orders'));

        $this->actingAs($waiter)->put(route('admin.orders.status', $order), [
            'status' => OrderStatus::READY->value,
        ])->assertRedirect(route('waiter.orders'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::READY->value,
        ]);
    }

    public function test_waiter_cannot_skip_order_status(): void
    {
        $waiter = $this->waiter();
        $order = $this->order();

        $this->actingAs($waiter)->put(route('admin.orders.status', $order), [
            'status' => OrderStatus::READY->value,
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PENDING->value,
        ]);
    }

    public function test_only_ready_orders_can_be_delivered(): void
    {
        $waiter = $this->waiter();
        $order = $this->order(OrderStatus::PREPARING);

        $this->actingAs($waiter)->put(route('admin.orders.deliver', $order))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PREPARING->value,
        ]);
    }
}

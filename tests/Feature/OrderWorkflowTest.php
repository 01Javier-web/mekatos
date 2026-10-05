<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function waiter(): User
    {
        return User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    }

    private function order(OrderStatus $status = OrderStatus::PENDING): Order
    {
        return Order::create(['type' => OrderType::TAKEAWAY, 'status' => $status, 'subtotal' => 10000, 'tax' => 0, 'total' => 10000]);
    }

    public function test_status_endpoint_no_longer_allows_manual_preparing_or_delivered(): void
    {
        // Ya no hay pasos manuales: PENDIENTE → ENTREGADO ocurre al imprimir las comandas.
        $admin = $this->admin();
        $order = $this->order();

        foreach ([OrderStatus::PREPARING, OrderStatus::DELIVERED, OrderStatus::TO_COLLECT] as $status) {
            $this->actingAs($admin)->put(route('admin.orders.status', $order), ['status' => $status->value])->assertSessionHasErrors('status');
        }

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::PENDING->value]);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_manual_mark_as_ready_step_no_longer_exists(): void
    {
        $waiter = $this->waiter();
        $admin = $this->admin();
        $order = $this->order();

        $this->assertFalse(Route::has('admin.orders.deliver'));
        $this->actingAs($waiter)->put('/admin/orders/'.$order->id.'/deliver')->assertNotFound();
        $this->actingAs($admin)->put('/admin/orders/'.$order->id.'/deliver')->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::PENDING->value]);
    }
    public function test_completed_status_cannot_be_set_from_status_endpoint(): void
    {
        $admin = $this->admin();
        $order = $this->order(OrderStatus::DELIVERED);

        $this->actingAs($admin)->put(route('admin.orders.status', $order), ['status' => OrderStatus::COMPLETED->value])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::DELIVERED->value]);
    }
}

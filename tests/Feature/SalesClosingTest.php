<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_closing_the_day_resets_the_order_id_sequence(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $firstOrder = Order::create([
            'table_session_id' => null,
            'type' => OrderType::TAKEAWAY,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 10000,
            'tax' => 0,
            'total' => 10000,
            'notes' => null,
            'handled_by_user_id' => $admin->id,
            'paid_at' => now(),
            'paid_by_user_id' => $admin->id,
        ]);

        $this->assertSame(1, $firstOrder->id);

        $this->actingAs($admin)
            ->post('/admin/reports/daily/close')
            ->assertOk();

        $this->assertDatabaseCount('orders', 0);

        $nextOrder = Order::create([
            'table_session_id' => null,
            'type' => OrderType::TAKEAWAY,
            'status' => OrderStatus::PENDING,
            'subtotal' => 5000,
            'tax' => 0,
            'total' => 5000,
            'notes' => null,
            'handled_by_user_id' => $admin->id,
        ]);

        $this->assertSame(1, $nextOrder->id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use App\TableSessionStatus;
use App\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_endpoint_creates_an_active_session_and_marks_table_occupied(): void
    {
        $table = RestaurantTable::create([
            'number' => 7,
            'name' => 'Mesa 7',
            'capacity' => 4,
            'qr_token' => 'qr-test-7',
            'status' => TableStatus::AVAILABLE,
        ]);

        $response = $this->getJson('/api/table/qr-test-7');

        $response->assertOk()
            ->assertJson([
                'id' => $table->id,
                'number' => 7,
                'status' => TableStatus::OCCUPIED->value,
            ])
            ->assertJsonStructure(['id', 'number', 'status', 'session_id']);

        $this->assertDatabaseHas('table_sessions', [
            'restaurant_table_id' => $table->id,
            'status' => TableSessionStatus::Active->value,
        ]);

        $this->assertSame(TableStatus::OCCUPIED, $table->fresh()->status);
    }

    public function test_table_endpoint_returns_not_found_for_an_unknown_token(): void
    {
        $this->getJson('/api/table/does-not-exist')
            ->assertNotFound();
    }
}

<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use App\Models\User;
use App\UserRole;
use Database\Seeders\RestaurantTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_seeder_creates_41_tables(): void
    {
        $this->seed(RestaurantTableSeeder::class);

        $this->assertDatabaseCount('restaurant_tables', 41);
        $this->assertDatabaseHas('restaurant_tables', ['number' => 1]);
        $this->assertDatabaseHas('restaurant_tables', ['number' => 41]);
    }

    public function test_admin_tables_interface_renders_table_grid(): void
    {
        $this->seed(RestaurantTableSeeder::class);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.tables.index'));

        $response->assertOk()
            ->assertSee('table-grid')
            ->assertSee('table-card-available')
            ->assertSee('Mesa 1')
            ->assertSee('Mesa 41')
            ->assertSee('Ver QR')
            ->assertSee('Abrir menú')
            ->assertSee('Copiar enlace');
    }
}

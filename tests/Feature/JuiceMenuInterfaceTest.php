<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\TableStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JuiceMenuInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_menu_exposes_the_natural_juice_options(): void
    {
        $category = Category::create([
            'name' => 'Jugos y Bebidas Preparadas',
            'description' => 'Jugos',
            'sort_order' => 16,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Jugo Natural Jarra',
            'description' => 'En agua $8.500 o en leche $9.500.',
            'price' => 8500,
            'is_available' => true,
        ]);
        $table = RestaurantTable::create([
            'number' => 1,
            'capacity' => 4,
            'qr_token' => 'juice-ui-token',
            'status' => TableStatus::AVAILABLE,
        ]);

        $this->get(route('client.table', $table->qr_token))
            ->assertOk()
            ->assertSee('Personaliza tu jugo')
            ->assertSee('En agua · $8.500')
            ->assertSee('En leche · $9.500')
            ->assertSee('Maracuyá')
            ->assertSee('Lulo')
            ->assertSee('Mora')
            ->assertSee('Fresa')
            ->assertSee('Otro');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\MekatosMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MekatosMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_removes_products_not_in_the_current_physical_menu_when_they_have_no_history(): void
    {
        $category = Category::create([
            'name' => 'Categoría antigua',
            'description' => 'No pertenece a la carta actual.',
            'sort_order' => 99,
            'is_active' => true,
        ]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Producto inexistente',
            'description' => 'Producto de prueba que no está en la carta.',
            'price' => 9999,
            'is_available' => true,
        ]);

        $this->seed(MekatosMenuSeeder::class);

        $this->assertDatabaseMissing('products', ['name' => 'Producto inexistente']);
        $this->assertDatabaseHas('products', ['name' => 'Jugo Natural Jarra', 'is_available' => 1]);
        $this->assertDatabaseMissing('products', ['name' => 'Jugo Natural Jarra - En Agua']);
        $this->assertDatabaseMissing('products', ['name' => 'Jugo Natural Jarra - En Leche']);
    }
}

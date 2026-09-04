<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_returns_categories_with_only_available_products(): void
    {
        $category = Category::create([
            'name' => 'Hamburguesas',
            'description' => 'Pruebas',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Hamburguesa disponible',
            'description' => 'Producto visible',
            'price' => 18000,
            'is_available' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Producto agotado',
            'description' => 'Producto oculto',
            'price' => 12000,
            'is_available' => false,
        ]);

        $response = $this->getJson('/api/menu');

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Hamburguesa disponible'])
            ->assertJsonMissing(['name' => 'Producto agotado']);
    }
}

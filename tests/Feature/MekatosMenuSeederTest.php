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

    public function test_seeder_sets_allows_sauces_for_food_but_not_for_beverages(): void
    {
        $this->seed(MekatosMenuSeeder::class);

        foreach (['Perro Sencillo', 'Salchipapa', 'Ensalada César', 'Alitas BBQ'] as $food) {
            $this->assertDatabaseHas('products', ['name' => $food, 'allows_sauces' => 1]);
        }
        foreach (['Jugo Natural Jarra', 'Limonada Jarra', 'Gaseosa 350 ml', 'Cerveza', 'Granizada de Mora', 'Cerezada'] as $beverage) {
            $this->assertDatabaseHas('products', ['name' => $beverage, 'allows_sauces' => 0]);
        }

        $beverageCategories = Category::whereIn('name', ['Jugos y Bebidas Preparadas', 'Gaseosas y Agua', 'Cerveza', 'Granizadas'])->pluck('id');
        $this->assertSame(0, Product::whereIn('category_id', $beverageCategories)->where('allows_sauces', true)->count());
        $this->assertSame(0, Product::whereNotIn('category_id', $beverageCategories)->where('allows_sauces', false)->count());
    }
}

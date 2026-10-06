<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CabbageSaladPortionsTest extends TestCase
{
    use RefreshDatabase;

    private const PORTIONS = [
        'Porción de ensalada de repollo' => 2500,
        'Medio litro de ensalada de repollo' => 20000,
        'Un litro de ensalada de repollo' => 30000,
    ];

    public function test_cabbage_salad_portions_exist_in_the_portions_category_with_their_prices(): void
    {
        $category = Category::whereRaw('LOWER(name) = ?', ['porciones'])->firstOrFail();

        foreach (self::PORTIONS as $name => $price) {
            $product = Product::where('name', $name)->firstOrFail();
            $this->assertSame($category->id, $product->category_id, $name);
            $this->assertSame($price, (int) $product->price, $name);
            $this->assertTrue($product->is_portion, $name);
            $this->assertTrue($product->is_available, $name);
            $this->assertSame(1, Product::where('name', $name)->count(), $name);
        }
    }

    public function test_migration_does_not_duplicate_portions_when_run_again(): void
    {
        $before = Product::count();

        (require database_path('migrations/2026_10_05_000004_add_cabbage_salad_portions.php'))->up();

        $this->assertSame($before, Product::count());
    }

    public function test_cabbage_salad_portions_appear_and_can_be_ordered_like_other_portions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $html = $this->actingAs($admin)->get(route('admin.orders.create'))->assertOk()->getContent();

        foreach (array_keys(self::PORTIONS) as $name) {
            $product = Product::where('name', $name)->firstOrFail();
            $this->assertStringContainsString($name, $html);
            $this->assertStringContainsString('data-portion-options="'.$product->id.'"', $html, $name);
        }

        $salad = Product::where('name', 'Un litro de ensalada de repollo')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => 'PARA_LLEVAR',
            'items' => [$salad->id => 1],
        ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(30000, (int) $order->subtotal);
        $this->assertSame(0, (int) $order->packaging_fee);
        $this->assertSame(30000, (int) $order->total);
    }
}

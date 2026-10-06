<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderSauce;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sauce;
use App\Models\User;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * "Permite salsas" es una propiedad explícita del producto, independiente del icopor:
 * bebidas y granizados pueden llevar icopor pero nunca salsas.
 */
class SauceProductPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $perro;

    private Product $gaseosa;

    private Product $limonada;

    private Product $granizado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $perros = Category::create(['name' => 'Perros', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $gaseosas = Category::create(['name' => 'Gaseosas y Agua', 'description' => null, 'sort_order' => 2, 'is_active' => true]);
        $jugos = Category::create(['name' => 'Jugos y Bebidas Preparadas', 'description' => null, 'sort_order' => 3, 'is_active' => true]);
        $granizadas = Category::create(['name' => 'Granizadas', 'description' => null, 'sort_order' => 4, 'is_active' => true]);
        $this->perro = Product::create(['category_id' => $perros->id, 'name' => 'Perro caliente', 'description' => null, 'price' => 12000, 'is_available' => true, 'allows_sauces' => true]);
        $this->gaseosa = Product::create(['category_id' => $gaseosas->id, 'name' => 'Coca-Cola 400 ml', 'description' => null, 'price' => 4000, 'is_available' => true]);
        $this->limonada = Product::create(['category_id' => $jugos->id, 'name' => 'Limonada Jarra', 'description' => null, 'price' => 10000, 'is_available' => true]);
        $this->granizado = Product::create(['category_id' => $granizadas->id, 'name' => 'Granizada de Mora', 'description' => null, 'price' => 8000, 'is_available' => true]);
    }

    private function s(string $name): int
    {
        return Sauce::where('name', $name)->value('id');
    }

    private function create(OrderType $type, array $data)
    {
        $data['type'] = $type->value;
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }
        if ($type === OrderType::TABLE) {
            $data['table_id'] = RestaurantTable::firstOrCreate(['qr_token' => 'mesa-1'], ['number' => 1, 'capacity' => 4, 'status' => TableStatus::AVAILABLE])->id;
        }

        return $this->actingAs($this->admin)->post(route('admin.orders.store'), $data);
    }

    private function assertNothingSaved(): void
    {
        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderSauce::count());
    }

    // 1. Producto que permite salsas

    public function test_product_that_allows_sauces_can_save_them(): void
    {
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 1],
            'sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, OrderSauce::whereNotNull('order_item_id')->count());
    }

    // 2. Bebida

    public function test_beverage_rejects_sauces(): void
    {
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 1, $this->gaseosa->id => 1],
            'sauces' => [$this->gaseosa->id => [$this->s('Tomate') => 'APARTE']],
        ])->assertSessionHasErrors(['sauces' => 'Estos productos no admiten salsas: Coca-Cola 400 ml.']);

        $this->assertNothingSaved();
    }

    // 3. Granizado

    public function test_granizado_rejects_sauces_even_with_icopor(): void
    {
        $this->create(OrderType::DELIVERY, [
            'items' => [$this->granizado->id => 1],
            'sauces' => [$this->granizado->id => [$this->s('Miel') => 'APARTE']],
        ])->assertSessionHasErrors('sauces');

        $this->assertNothingSaved();
    }

    // 4. Request manipulado a mano

    public function test_manipulated_request_cannot_save_sauces_on_a_product_without_permission(): void
    {
        // Por unidad (modo personalizado) en un producto con icopor y sin permiso.
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->limonada->id => 2],
            'sauce_mode' => [$this->limonada->id => 'each'],
            'unit_sauces' => [$this->limonada->id => [0 => [], 1 => [$this->s('BBQ') => 'EN_PRODUCTO']]],
        ])->assertSessionHasErrors('sauces');
        $this->assertNothingSaved();

        // Mezclado con un producto que sí permite: se rechaza todo el pedido.
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 1, $this->limonada->id => 1],
            'sauces' => [
                $this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO'],
                $this->limonada->id => [$this->s('Rosada') => 'APARTE'],
            ],
        ])->assertSessionHasErrors('sauces');
        $this->assertNothingSaved();
    }

    // 5. Adición

    public function test_addition_rejects_sauces_for_a_product_without_permission(): void
    {
        $this->create(OrderType::TAKEAWAY, ['items' => [$this->perro->id => 1]])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();
        $before = [$order->fresh()->total, $order->orderItems()->count()];

        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), [
            'items' => [$this->granizado->id => 2],
            'sauce_mode' => [$this->granizado->id => 'each'],
            'unit_sauces' => [$this->granizado->id => [0 => [$this->s('Miel') => 'APARTE']]],
        ])->assertSessionHasErrors('sauces');

        $this->assertSame($before, [$order->fresh()->total, $order->orderItems()->count()]);
        $this->assertSame(1, $order->rounds()->count());
        $this->assertSame(0, OrderSauce::count());

        // La adición del mismo producto sin salsas sí funciona, con su icopor.
        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), [
            'items' => [$this->granizado->id => 2],
        ])->assertSessionHasNoErrors();
        $this->assertSame(2 * 1500, (int) $order->fresh()->packaging_fee);
    }

    // 6. Formularios: sin selector para productos sin permiso, aunque la cantidad sea > 1

    public function test_forms_only_render_the_sauce_picker_for_products_that_allow_sauces(): void
    {
        // Error de validación a propósito para que el formulario vuelva con cantidades > 1.
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 3, $this->granizado->id => 3, $this->gaseosa->id => 2],
            'sauces' => [$this->granizado->id => [$this->s('Miel') => 'APARTE']],
        ])->assertSessionHasErrors('sauces');

        $html = $this->actingAs($this->admin)->get(route('admin.orders.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-sauce-picker data-product-id="'.$this->perro->id.'"', $html);
        $this->assertStringContainsString('name="sauce_mode['.$this->perro->id.']"', $html);
        foreach ([$this->granizado, $this->gaseosa, $this->limonada] as $product) {
            $this->assertStringNotContainsString('data-product-id="'.$product->id.'"', $html, $product->name);
            $this->assertStringNotContainsString('name="sauce_mode['.$product->id.']"', $html, $product->name);
        }

        $this->create(OrderType::DELIVERY, ['items' => [$this->perro->id => 1]])->assertSessionHasNoErrors();
        $add = $this->actingAs($this->waiter)->get(route('admin.orders.add', Order::firstOrFail()))->assertOk()->getContent();
        $this->assertStringContainsString('data-sauce-picker data-product-id="'.$this->perro->id.'"', $add);
        foreach ([$this->granizado, $this->gaseosa, $this->limonada] as $product) {
            $this->assertStringNotContainsString('data-product-id="'.$product->id.'"', $add, $product->name);
        }
    }

    // 7. Salsas generales

    public function test_general_sauces_still_work_with_only_beverages_in_the_order(): void
    {
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->granizado->id => 1, $this->gaseosa->id => 1],
            'general_sauces' => [$this->s('Tomate'), $this->s('Mayonesa')],
        ])->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(['Tomate', 'Mayonesa'], $order->generalSauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all());
        $this->assertSame(0, OrderSauce::whereNotNull('order_item_id')->count());
    }

    // 8. Icopor de bebidas y granizados intacto

    public function test_icopor_of_beverages_and_granizados_is_unchanged(): void
    {
        $this->create(OrderType::TAKEAWAY, ['items' => [$this->limonada->id => 2, $this->granizado->id => 1, $this->gaseosa->id => 1, $this->perro->id => 1]])
            ->assertSessionHasNoErrors();
        $this->assertSame(3 * 1500, (int) Order::latest('id')->first()->packaging_fee);

        $this->create(OrderType::DELIVERY, ['items' => [$this->granizado->id => 2]])->assertSessionHasNoErrors();
        $this->assertSame(2 * 1500, (int) Order::latest('id')->first()->packaging_fee);

        $this->create(OrderType::TABLE, ['items' => [$this->granizado->id => 2]])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) Order::latest('id')->first()->packaging_fee);
    }

    // 9. MESA

    public function test_table_orders_still_reject_sauces_even_on_products_that_allow_them(): void
    {
        $this->create(OrderType::TABLE, [
            'items' => [$this->perro->id => 1],
            'sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']],
        ])->assertSessionHasErrors('sauces');

        $this->assertNothingSaved();
    }

    // ADMIN configura la propiedad al crear o editar productos

    public function test_admin_configures_allows_sauces_when_creating_and_editing_products(): void
    {
        $categoryId = $this->perro->category_id;

        $this->actingAs($this->admin)->get(route('admin.settings.products.create'))->assertOk()
            ->assertSee('name="allows_sauces"', false);
        $this->actingAs($this->admin)->post(route('admin.settings.products.store'), [
            'category_id' => $categoryId, 'name' => 'Perro nuevo', 'price' => 13000, 'is_available' => '1', 'allows_sauces' => '1',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.settings.products.store'), [
            'category_id' => $categoryId, 'name' => 'Bebida nueva', 'price' => 5000, 'is_available' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Product::where('name', 'Perro nuevo')->value('allows_sauces'));
        $this->assertFalse(Product::where('name', 'Bebida nueva')->value('allows_sauces'));

        $this->actingAs($this->admin)->get(route('admin.settings.products.edit', $this->perro))->assertOk()
            ->assertSee('name="allows_sauces" value="1" checked', false);
        $this->actingAs($this->admin)->put(route('admin.settings.products.update', $this->perro), [
            'category_id' => $categoryId, 'name' => 'Perro caliente', 'price' => 12000, 'is_available' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($this->perro->fresh()->allows_sauces);

        $this->actingAs($this->waiter)->post(route('admin.settings.products.store'), [
            'category_id' => $categoryId, 'name' => 'Intento mesero', 'price' => 1, 'allows_sauces' => '1',
        ])->assertForbidden();
    }

    public function test_new_products_do_not_allow_sauces_by_default(): void
    {
        $this->assertFalse($this->gaseosa->fresh()->allows_sauces);
    }

    // Migración: los productos existentes que no son bebidas quedan con "Permite salsas"

    public function test_migration_marks_existing_non_beverage_products_as_allowing_sauces(): void
    {
        $migration = require database_path('migrations/2026_10_05_000003_add_allows_sauces_to_products.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('products', 'allows_sauces'));

        $migration->up();

        $this->assertSame(
            ['Coca-Cola 400 ml' => 0, 'Granizada de Mora' => 0, 'Limonada Jarra' => 0, 'Perro caliente' => 1],
            DB::table('products')->whereIn('id', [$this->perro->id, $this->gaseosa->id, $this->limonada->id, $this->granizado->id])->orderBy('name')->pluck('allows_sauces', 'name')->map(fn ($v) => (int) $v)->all()
        );
    }
}

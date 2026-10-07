<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Models\BeverageOption;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSauce;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sauce;
use App\Models\TableSession;
use App\Models\User;
use App\TableSessionStatus;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opciones por unidad ("Todos iguales" / "Personalizar individualmente") para jugos, bebidas con
 * opciones y combos, en Nuevo pedido y Editar. Las unidades idénticas (opciones, nota, precio y
 * salsas) se agrupan en una línea ×N; las distintas quedan en líneas separadas.
 */
class OrderUnitOptionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $juice;

    private Product $gaseosa;

    private Product $hamburguesa;

    private Product $perro;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $jugos = Category::create(['name' => 'Jugos y Bebidas Preparadas', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $bebidas = Category::create(['name' => 'Gaseosas y Agua', 'description' => null, 'sort_order' => 2, 'is_active' => true]);
        $hamburguesas = Category::create(['name' => 'Hamburguesas', 'description' => null, 'sort_order' => 3, 'is_active' => true]);
        $comidas = Category::create(['name' => 'Comidas', 'description' => null, 'sort_order' => 4, 'is_active' => true]);
        $this->juice = Product::create(['category_id' => $jugos->id, 'name' => 'Jugo Natural Jarra', 'description' => null, 'price' => 8500, 'is_available' => true]);
        $this->gaseosa = Product::create(['category_id' => $bebidas->id, 'name' => 'Gaseosa 350 ml', 'description' => null, 'price' => 4500, 'is_available' => true]);
        foreach (['Coca-Cola', 'Sprite', 'Quatro'] as $i => $name) {
            BeverageOption::create(['product_id' => $this->gaseosa->id, 'name' => $name, 'sort_order' => $i + 1, 'is_available' => true]);
        }
        $this->hamburguesa = Product::create(['category_id' => $hamburguesas->id, 'name' => 'Hamburguesa Sencilla', 'description' => null, 'price' => 23500, 'is_available' => true, 'allows_sauces' => true]);
        $this->perro = Product::create(['category_id' => $comidas->id, 'name' => 'Perro Sencillo', 'description' => null, 'price' => 13000, 'is_available' => true, 'allows_sauces' => true]);
        $this->table = RestaurantTable::create(['number' => 7, 'capacity' => 4, 'qr_token' => 'mesa-7', 'status' => TableStatus::AVAILABLE]);
    }

    private function orderData(OrderType $type, array $data): array
    {
        $data = ['type' => $type->value] + $data;
        if ($type === OrderType::TABLE) {
            $data['table_id'] = $this->table->id;
        }
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }

        return $data;
    }

    private function store(OrderType $type, array $data): Order
    {
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->orderData($type, $data))->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    /** Tres jugos personalizados: [preparación, fruta, otra fruta]. */
    private function juiceUnits(array $units): array
    {
        return array_map(fn (array $u) => ['juice_preparation' => $u[0], 'juice_fruit' => $u[1], 'juice_other_fruit' => $u[2] ?? null], $units);
    }

    /** [cantidad, precio unitario, total, nota] de las líneas activas de un producto. */
    private function lines(Order $order, Product $product): array
    {
        return OrderItem::where('order_id', $order->id)->where('product_id', $product->id)->whereNull('voided_at')->orderBy('id')->get()
            ->map(fn (OrderItem $i) => [(int) $i->quantity, (int) $i->unit_price, (int) $i->total, $i->notes])->all();
    }

    private function s(string $name): int
    {
        return Sauce::where('name', $name)->value('id');
    }

    // --- Jugos ------------------------------------------------------------------------------

    public function test_three_different_juices_create_three_lines_in_every_order_type(): void
    {
        foreach ([OrderType::TABLE, OrderType::TAKEAWAY, OrderType::DELIVERY] as $type) {
            $order = $this->store($type, [
                'items' => [$this->juice->id => 3],
                'option_mode' => [$this->juice->id => 'each'],
                'unit_options' => [$this->juice->id => $this->juiceUnits([['AGUA', 'LULO'], ['LECHE', 'MORA'], ['AGUA', 'FRESA']])],
            ]);

            $this->assertSame([
                [1, 8500, 8500, 'En agua · Lulo'],
                [1, 9500, 9500, 'En leche · Mora'],
                [1, 8500, 8500, 'En agua · Fresa'],
            ], $this->lines($order, $this->juice), $type->value);
            $packaging = $type === OrderType::TABLE ? 0 : 3 * 1500;
            $delivery = $type === OrderType::DELIVERY ? 3000 : 0;
            $this->assertSame(26500, (int) $order->subtotal, $type->value);
            $this->assertSame($packaging, (int) $order->packaging_fee, $type->value.' icopor por cantidad total');
            $this->assertSame(26500 + $packaging + $delivery, (int) $order->total, $type->value);
        }
    }

    public function test_two_equal_and_one_different_juice_are_grouped(): void
    {
        $order = $this->store(OrderType::TAKEAWAY, [
            'items' => [$this->juice->id => 3],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['AGUA', 'LULO'], ['LECHE', 'MORA'], ['AGUA', 'LULO']])],
        ]);

        $this->assertSame([
            [2, 8500, 17000, 'En agua · Lulo'],
            [1, 9500, 9500, 'En leche · Mora'],
        ], $this->lines($order, $this->juice));
        $this->assertSame(26500, (int) $order->subtotal);
        $this->assertSame(4500, (int) $order->packaging_fee);
    }

    public function test_all_equal_juices_keep_a_single_line_in_both_modes(): void
    {
        $same = $this->store(OrderType::TAKEAWAY, [
            'items' => [$this->juice->id => 3],
            'juice_preparation' => [$this->juice->id => 'LECHE'],
            'juice_fruit' => [$this->juice->id => 'MORA'],
            'item_notes' => [$this->juice->id => 'Sin azúcar'],
        ]);
        $this->assertSame([[3, 9500, 28500, 'En leche · Mora · Sin azúcar']], $this->lines($same, $this->juice));

        $each = $this->store(OrderType::TAKEAWAY, [
            'items' => [$this->juice->id => 3],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['LECHE', 'MORA'], ['LECHE', 'MORA'], ['LECHE', 'MORA']])],
            'item_notes' => [$this->juice->id => 'Sin azúcar'],
        ]);
        $this->assertSame([[3, 9500, 28500, 'En leche · Mora · Sin azúcar']], $this->lines($each, $this->juice));
    }

    public function test_same_mode_ignores_unit_options_and_single_unit_ignores_each_mode(): void
    {
        $order = $this->store(OrderType::TABLE, [
            'items' => [$this->juice->id => 2],
            'option_mode' => [$this->juice->id => 'same'],
            'juice_preparation' => [$this->juice->id => 'AGUA'],
            'juice_fruit' => [$this->juice->id => 'LULO'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['LECHE', 'MORA'], ['LECHE', 'FRESA']])],
        ]);
        $this->assertSame([[2, 8500, 17000, 'En agua · Lulo']], $this->lines($order, $this->juice));

        $single = $this->store(OrderType::TABLE, [
            'items' => [$this->juice->id => 1],
            'option_mode' => [$this->juice->id => 'each'],
            'juice_preparation' => [$this->juice->id => 'LECHE'],
            'juice_fruit' => [$this->juice->id => 'FRESA'],
        ]);
        $this->assertSame([[1, 9500, 9500, 'En leche · Fresa']], $this->lines($single, $this->juice));
    }

    public function test_water_and_milk_prices_are_per_unit(): void
    {
        $order = $this->store(OrderType::TABLE, [
            'items' => [$this->juice->id => 3],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['AGUA', 'MARACUYA'], ['AGUA', 'MARACUYA'], ['LECHE', 'MARACUYA']])],
        ]);

        $this->assertSame([[2, 8500, 17000, 'En agua · Maracuyá'], [1, 9500, 9500, 'En leche · Maracuyá']], $this->lines($order, $this->juice));
        $this->assertSame(26500, (int) $order->total);
    }

    public function test_other_fruit_per_unit_uses_its_text_or_the_shared_detail(): void
    {
        $order = $this->store(OrderType::TABLE, [
            'items' => [$this->juice->id => 2],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['AGUA', 'OTRO', 'Guanábana'], ['LECHE', 'LULO']])],
        ]);
        $this->assertSame([[1, 8500, 8500, 'En agua · Otro: Guanábana'], [1, 9500, 9500, 'En leche · Lulo']], $this->lines($order, $this->juice));

        // Como en "Todos iguales": sin texto propio, la fruta se toma del detalle del producto.
        $fallback = $this->store(OrderType::TABLE, [
            'items' => [$this->juice->id => 2],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['AGUA', 'OTRO'], ['AGUA', 'MORA']])],
            'item_notes' => [$this->juice->id => 'Papaya'],
        ]);
        $this->assertSame([[1, 8500, 8500, 'En agua · Otro: Papaya'], [1, 8500, 8500, 'En agua · Mora · Papaya']], $this->lines($fallback, $this->juice));
    }

    public function test_other_fruit_without_text_is_rejected_and_nothing_is_saved(): void
    {
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->orderData(OrderType::TAKEAWAY, [
            'items' => [$this->juice->id => 2],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['AGUA', 'LULO'], ['AGUA', 'OTRO', '  ']])],
        ]))->assertSessionHasErrors(['items' => 'Si eliges "Otro", escribe la fruta en el apartado de detalles. (unidad 2)']);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
    }

    public function test_invalid_fruit_or_preparation_per_unit_is_rejected(): void
    {
        foreach ([['juice_preparation' => 'AGUA', 'juice_fruit' => 'BANANO'], ['juice_preparation' => 'CAFE', 'juice_fruit' => 'LULO']] as $bad) {
            $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->orderData(OrderType::TABLE, [
                'items' => [$this->juice->id => 2],
                'option_mode' => [$this->juice->id => 'each'],
                'unit_options' => [$this->juice->id => [['juice_preparation' => 'AGUA', 'juice_fruit' => 'LULO'], $bad]],
            ]))->assertSessionHasErrors();
        }

        // Una unidad sin opciones tampoco pasa.
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->orderData(OrderType::TABLE, [
            'items' => [$this->juice->id => 2],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => [['juice_preparation' => 'AGUA', 'juice_fruit' => 'LULO']]],
        ]))->assertSessionHasErrors(['items' => 'Selecciona si el jugo natural es en agua o en leche. (unidad 2)']);

        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->orderData(OrderType::TABLE, [
            'items' => [$this->juice->id => 2],
            'option_mode' => [$this->juice->id => 'raro'],
        ]))->assertSessionHasErrors('option_mode.'.$this->juice->id);

        $this->assertSame(0, Order::count());
    }

    // --- Bebidas con opciones ---------------------------------------------------------------

    public function test_beverages_with_different_options_are_split_and_equal_ones_grouped(): void
    {
        $order = $this->store(OrderType::TAKEAWAY, [
            'items' => [$this->gaseosa->id => 3],
            'option_mode' => [$this->gaseosa->id => 'each'],
            'unit_options' => [$this->gaseosa->id => [['beverage_option' => 'Coca-Cola'], ['beverage_option' => 'Sprite'], ['beverage_option' => 'Coca-Cola']]],
            'item_notes' => [$this->gaseosa->id => 'Fría'],
        ]);

        $this->assertSame([[2, 4500, 9000, 'Coca-Cola · Fría'], [1, 4500, 4500, 'Sprite · Fría']], $this->lines($order, $this->gaseosa));
        $this->assertSame(13500, (int) $order->subtotal);
        $this->assertSame(0, (int) $order->packaging_fee, 'La gaseosa no lleva icopor.');
    }

    public function test_invalid_or_missing_beverage_option_per_unit_is_rejected(): void
    {
        BeverageOption::where('name', 'Quatro')->update(['is_available' => false]);

        foreach (['Pepsi', 'Quatro', ''] as $bad) {
            $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->orderData(OrderType::TABLE, [
                'items' => [$this->gaseosa->id => 2],
                'option_mode' => [$this->gaseosa->id => 'each'],
                'unit_options' => [$this->gaseosa->id => [['beverage_option' => 'Coca-Cola'], ['beverage_option' => $bad]]],
            ]))->assertSessionHasErrors('items');
        }

        // Un producto sin opciones de bebida no acepta una por unidad.
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $this->orderData(OrderType::TABLE, [
            'items' => [$this->perro->id => 2],
            'option_mode' => [$this->perro->id => 'each'],
            'unit_options' => [$this->perro->id => [['beverage_option' => 'Coca-Cola'], []]],
        ]))->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
    }

    // --- Combos -----------------------------------------------------------------------------

    public function test_hamburgers_with_different_combo_configurations(): void
    {
        $order = $this->store(OrderType::DELIVERY, [
            'items' => [$this->hamburguesa->id => 3],
            'option_mode' => [$this->hamburguesa->id => 'each'],
            'unit_options' => [$this->hamburguesa->id => [
                ['combo' => 'SI', 'combo_beverage_type' => 'GASEOSA', 'combo_beverage_flavor' => 'Coca-Cola'],
                ['combo' => 'NO'],
                ['combo' => 'SI', 'combo_beverage_type' => 'GASEOSA', 'combo_beverage_flavor' => 'Sprite'],
            ]],
        ]);

        $this->assertSame([
            [1, 33500, 33500, 'COMBO +$10.000 · Papas a la francesa · Gaseosa personal · Coca-Cola'],
            [1, 23500, 23500, null],
            [1, 33500, 33500, 'COMBO +$10.000 · Papas a la francesa · Gaseosa personal · Sprite'],
        ], $this->lines($order, $this->hamburguesa));
        $this->assertSame(90500, (int) $order->subtotal);
        $this->assertSame(0, (int) $order->packaging_fee);
        $this->assertSame(90500 + 3000, (int) $order->total);
    }

    public function test_equal_combos_are_grouped_and_sauces_also_separate_units(): void
    {
        $combo = ['combo' => 'SI', 'combo_beverage_type' => 'GASEOSA', 'combo_beverage_flavor' => 'Coca-Cola'];
        $order = $this->store(OrderType::TAKEAWAY, [
            'items' => [$this->hamburguesa->id => 3],
            'option_mode' => [$this->hamburguesa->id => 'each'],
            'unit_options' => [$this->hamburguesa->id => [$combo, $combo, $combo]],
            'sauce_mode' => [$this->hamburguesa->id => 'each'],
            'unit_sauces' => [$this->hamburguesa->id => [0 => [$this->s('BBQ') => 'EN_PRODUCTO'], 1 => [], 2 => [$this->s('BBQ') => 'EN_PRODUCTO']]],
        ]);

        $note = 'COMBO +$10.000 · Papas a la francesa · Gaseosa personal · Coca-Cola';
        $this->assertSame([[2, 33500, 67000, $note], [1, 33500, 33500, $note]], $this->lines($order, $this->hamburguesa));
        $items = OrderItem::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame([$this->s('BBQ')], OrderSauce::where('order_item_id', $items[0]->id)->pluck('sauce_id')->all());
        $this->assertSame(0, OrderSauce::where('order_item_id', $items[1]->id)->count());
    }

    // --- Salsas -----------------------------------------------------------------------------

    public function test_unit_sauces_keep_working_without_unit_options(): void
    {
        $order = $this->store(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 3],
            'sauce_mode' => [$this->perro->id => 'each'],
            'unit_sauces' => [$this->perro->id => [0 => [$this->s('BBQ') => 'EN_PRODUCTO'], 1 => [$this->s('BBQ') => 'EN_PRODUCTO'], 2 => [$this->s('Miel') => 'APARTE']]],
        ]);

        $this->assertSame([[2, 13000, 26000, null], [1, 13000, 13000, null]], $this->lines($order, $this->perro));
        $items = OrderItem::where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame(['EN_PRODUCTO'], OrderSauce::where('order_item_id', $items[0]->id)->pluck('placement')->all());
        $this->assertSame([$this->s('Miel')], OrderSauce::where('order_item_id', $items[1]->id)->pluck('sauce_id')->all());

        $same = $this->store(OrderType::DELIVERY, [
            'items' => [$this->perro->id => 2],
            'sauces' => [$this->perro->id => [$this->s('BBQ') => 'EN_PRODUCTO']],
        ]);
        $item = OrderItem::where('order_id', $same->id)->sole();
        $this->assertSame(2, (int) $item->quantity);
        $this->assertSame(1, OrderSauce::where('order_item_id', $item->id)->count());
    }

    // --- Editar -----------------------------------------------------------------------------

    public function test_edit_adds_individually_configured_units_with_prices_and_notes(): void
    {
        $order = $this->store(OrderType::TAKEAWAY, ['items' => [$this->perro->id => 1]]);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->actingAs($this->waiter)->post(route('admin.orders.update', $order), [
            'items' => [$this->juice->id => 3, $this->gaseosa->id => 2],
            'option_mode' => [$this->juice->id => 'each', $this->gaseosa->id => 'each'],
            'unit_options' => [
                $this->juice->id => $this->juiceUnits([['AGUA', 'LULO'], ['LECHE', 'MORA'], ['AGUA', 'LULO']]),
                $this->gaseosa->id => [['beverage_option' => 'Coca-Cola'], ['beverage_option' => 'Sprite']],
            ],
            'item_notes' => [$this->juice->id => 'Poco hielo'],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame([[2, 8500, 17000, 'En agua · Lulo · Poco hielo'], [1, 9500, 9500, 'En leche · Mora · Poco hielo']], $this->lines($order, $this->juice));
        $this->assertSame([[1, 4500, 4500, 'Coca-Cola'], [1, 4500, 4500, 'Sprite']], $this->lines($order, $this->gaseosa));
        $this->assertSame(13000 + 26500 + 9000, (int) $order->subtotal);
        $this->assertSame(3 * 1500, (int) $order->packaging_fee, 'Icopor por los 3 jugos agregados.');
        $this->assertSame(13000 + 26500 + 9000 + 4500, (int) $order->total);

        $html = $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk()->getContent();
        $this->assertStringContainsString('En agua · Lulo · Poco hielo', $html);
        $this->assertStringContainsString('En leche · Mora · Poco hielo', $html);
        // Solo agrega (no quita nada): sale como adición, sin "NO PREPARAR".
        $this->assertStringContainsString('ADICIÓN #2', $html);
        $this->assertStringNotContainsString('NO PREPARAR', $html);
    }

    public function test_edit_substitutes_a_juice_with_a_different_configuration(): void
    {
        $order = $this->store(OrderType::TABLE, [
            'items' => [$this->juice->id => 2],
            'juice_preparation' => [$this->juice->id => 'AGUA'],
            'juice_fruit' => [$this->juice->id => 'LULO'],
        ]);
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();
        $line = OrderItem::where('order_id', $order->id)->sole();

        // Quitar los 2 jugos en agua y poner 1 en agua de mora + 1 en leche de fresa.
        $this->actingAs($this->waiter)->post(route('admin.orders.update', $order), [
            'void' => [$line->id => 2],
            'items' => [$this->juice->id => 2],
            'option_mode' => [$this->juice->id => 'each'],
            'unit_options' => [$this->juice->id => $this->juiceUnits([['AGUA', 'MORA'], ['LECHE', 'FRESA']])],
            'reason' => 'Cambio de jugos',
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(1, Order::count(), 'Conserva el mismo pedido.');
        $this->assertNotNull($line->fresh()->voided_at);
        $this->assertSame([[1, 8500, 8500, 'En agua · Mora'], [1, 9500, 9500, 'En leche · Fresa']], $this->lines($order, $this->juice));
        $this->assertSame(18000, (int) $order->total, 'MESA: sin icopor.');
        $this->assertSame(0, (int) $order->packaging_fee);
        $history = (string) $order->statusHistories()->latest('id')->value('notes');
        $this->assertStringContainsString('Diferencia: +$1.000', $history);

        $html = $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk()->getContent();
        $this->assertStringContainsString('❌ NO PREPARAR', $html);
        $this->assertStringContainsString('✅ PREPARAR', $html);
        $this->assertStringContainsString('En leche · Fresa', $html);
    }

    // --- Páginas -----------------------------------------------------------------------------

    public function test_new_and_edit_pages_render_the_option_mode_selector(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.orders.create'))->assertOk()->getContent();
        foreach ([$this->juice, $this->gaseosa, $this->hamburguesa] as $product) {
            $this->assertStringContainsString('name="option_mode['.$product->id.']"', $html);
        }
        $this->assertStringNotContainsString('name="option_mode['.$this->perro->id.']"', $html);
        $this->assertStringContainsString('Personalizar individualmente', $html);

        $order = $this->store(OrderType::TAKEAWAY, ['items' => [$this->perro->id => 1]]);
        $edit = $this->actingAs($this->admin)->get(route('admin.orders.edit', $order))->assertOk()->getContent();
        $this->assertStringContainsString('name="option_mode['.$this->juice->id.']"', $edit);
        $this->assertStringContainsString('function syncUnitOptions', $edit);
    }

    // --- QR ---------------------------------------------------------------------------------

    public function test_qr_menu_uses_a_cart_key_per_juice_configuration_and_api_keeps_lines(): void
    {
        $menu = file_get_contents(resource_path('views/client/menu.blade.php'));
        $this->assertStringContainsString('function juiceKey(id,o)', $menu);
        $this->assertStringNotContainsString('const key=String(pendingJuiceId)', $menu);

        $this->table->update(['status' => TableStatus::OCCUPIED]);
        $session = TableSession::create(['restaurant_table_id' => $this->table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);
        $this->postJson('/api/orders', [
            'table_session_id' => $session->id, 'table_token' => $this->table->qr_token,
            'items' => [
                ['product_id' => $this->juice->id, 'quantity' => 1, 'juice_options' => ['preparation' => 'AGUA', 'fruit' => 'LULO']],
                ['product_id' => $this->juice->id, 'quantity' => 1, 'juice_options' => ['preparation' => 'LECHE', 'fruit' => 'MORA']],
            ],
        ])->assertCreated();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame([[1, 8500, 8500, 'En agua · Lulo'], [1, 9500, 9500, 'En leche · Mora']], $this->lines($order, $this->juice));
    }
}

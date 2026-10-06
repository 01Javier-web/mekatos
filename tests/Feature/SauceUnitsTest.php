<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSauce;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sauce;
use App\Models\User;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Salsas por unidad: "Todos iguales" (una línea ×N) o "Personalizar individualmente"
 * (cada configuración distinta en su propia línea; las iguales se agrupan).
 */
class SauceUnitsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $perro;

    private Product $salchipapa;

    private Product $granizado;

    private Product $porcion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $perros = Category::create(['name' => 'Perros', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $granizadas = Category::create(['name' => 'Granizadas', 'description' => null, 'sort_order' => 2, 'is_active' => true]);
        $this->perro = Product::create(['category_id' => $perros->id, 'name' => 'Perro caliente', 'description' => null, 'price' => 12000, 'is_available' => true, 'allows_sauces' => true]);
        $this->salchipapa = Product::create(['category_id' => $perros->id, 'name' => 'Salchipapa', 'description' => null, 'price' => 15000, 'is_available' => true, 'allows_sauces' => true]);
        $this->granizado = Product::create(['category_id' => $granizadas->id, 'name' => 'Granizada de Mora', 'description' => null, 'price' => 8000, 'is_available' => true]);
        $this->porcion = Product::create(['category_id' => $perros->id, 'name' => 'Porción de papas', 'description' => null, 'price' => 6000, 'is_available' => true, 'is_portion' => true, 'allows_sauces' => true]);
    }

    private function s(string $name): int
    {
        return Sauce::where('name', $name)->value('id');
    }

    private function create(OrderType $type, array $data, ?User $user = null)
    {
        $data['type'] = $type->value;
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }
        if ($type === OrderType::TABLE) {
            $data['table_id'] = RestaurantTable::firstOrCreate(['qr_token' => 'mesa-1'], ['number' => 1, 'capacity' => 4, 'status' => TableStatus::AVAILABLE])->id;
        }

        return $this->actingAs($user ?? $this->admin)->post(route('admin.orders.store'), $data);
    }

    private function lastOrder(): Order
    {
        return Order::query()->latest('id')->firstOrFail();
    }

    /** Líneas del pedido: [producto, cantidad, total, "Salsa (UBICACIÓN), ..."] en orden de creación. */
    private function lines(Order $order, ?int $roundNumber = null): array
    {
        return OrderItem::query()->with(['product', 'sauces.sauce', 'round'])->where('order_id', $order->id)->orderBy('id')->get()
            ->when($roundNumber, fn ($items) => $items->filter(fn ($i) => $i->round?->number === $roundNumber))
            ->map(fn (OrderItem $i) => [
                $i->product->name,
                (int) $i->quantity,
                (int) $i->total,
                $i->sauces->map(fn ($s) => $s->sauce->name.' ('.$s->placement.')')->implode(', '),
            ])->values()->all();
    }

    private function threeDifferentPerros(): array
    {
        return [
            'sauce_mode' => [$this->perro->id => 'each'],
            'unit_sauces' => [$this->perro->id => [
                0 => [$this->s('Rosada') => 'EN_PRODUCTO', $this->s('Tártara') => 'APARTE'],
                1 => [$this->s('Mostaza') => 'EN_PRODUCTO'],
                2 => [$this->s('BBQ') => 'EN_PRODUCTO', $this->s('Piña') => 'APARTE'],
            ]],
        ];
    }

    // 1. Producto ×3 con las mismas salsas

    public function test_same_sauces_for_all_units_keep_a_single_line(): void
    {
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 3],
            'sauce_mode' => [$this->perro->id => 'same'],
            'sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']],
        ])->assertSessionHasNoErrors();

        $this->assertSame([['Perro caliente', 3, 36000, 'Rosada (EN_PRODUCTO)']], $this->lines($this->lastOrder()));
    }

    public function test_personalized_units_with_identical_configuration_are_grouped(): void
    {
        $rosada = [$this->s('Rosada') => 'EN_PRODUCTO'];
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 3],
            'sauce_mode' => [$this->perro->id => 'each'],
            'unit_sauces' => [$this->perro->id => [0 => $rosada, 1 => [$this->s('Mostaza') => 'APARTE'], 2 => $rosada]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            ['Perro caliente', 2, 24000, 'Rosada (EN_PRODUCTO)'],
            ['Perro caliente', 1, 12000, 'Mostaza (APARTE)'],
        ], $this->lines($this->lastOrder()));
    }

    // 2. Producto ×3 con tres configuraciones diferentes

    public function test_three_different_configurations_become_three_lines_with_the_same_total(): void
    {
        $this->create(OrderType::DELIVERY, ['items' => [$this->perro->id => 3, $this->salchipapa->id => 1]] + $this->threeDifferentPerros())
            ->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $this->assertSame([
            ['Perro caliente', 1, 12000, 'Rosada (EN_PRODUCTO), Tártara (APARTE)'],
            ['Perro caliente', 1, 12000, 'Mostaza (EN_PRODUCTO)'],
            ['Perro caliente', 1, 12000, 'Piña (APARTE), BBQ (EN_PRODUCTO)'],
            ['Salchipapa', 1, 15000, ''],
        ], $this->lines($order));

        // Precio, icopor y total idénticos a pedir 3 perros en una sola línea.
        $this->assertSame(36000 + 15000, (int) $order->subtotal);
        $this->assertSame(0, (int) $order->packaging_fee);
        $this->assertSame(36000 + 15000 + 3000, (int) $order->total);
    }

    public function test_personalized_packaged_product_keeps_icopor_per_unit(): void
    {
        // El granizado lleva icopor pero no admite salsas: personalizarlo no divide nada.
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->granizado->id => 3],
            'sauce_mode' => [$this->granizado->id => 'each'],
        ])->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $this->assertSame([['Granizada de Mora', 3, 24000, '']], $this->lines($order));
        $this->assertSame(3 * 1500, (int) $order->packaging_fee);
        $this->assertSame(24000 + 4500, (int) $order->total);
    }

    // 3. Productos sin salsas

    public function test_products_without_sauces_keep_a_single_line_even_when_personalized(): void
    {
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 3, $this->salchipapa->id => 2],
            'sauce_mode' => [$this->perro->id => 'each'],
            'unit_sauces' => [$this->perro->id => [0 => [], 1 => [], 2 => []]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([['Perro caliente', 3, 36000, ''], ['Salchipapa', 2, 30000, '']], $this->lines($this->lastOrder()));
        $this->assertSame(0, OrderSauce::count());
    }

    public function test_units_beyond_the_quantity_and_ignored_mode_inputs_are_not_used(): void
    {
        // Unidad 5 no existe (cantidad 2) y en modo "Todos iguales" se ignoran las unidades.
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 2],
            'sauce_mode' => [$this->perro->id => 'same'],
            'sauces' => [$this->perro->id => [$this->s('Tomate') => 'APARTE']],
            'unit_sauces' => [$this->perro->id => [5 => [$this->s('BBQ') => 'EN_PRODUCTO']]],
        ])->assertSessionHasNoErrors();

        $this->assertSame([['Perro caliente', 2, 24000, 'Tomate (APARTE)']], $this->lines($this->lastOrder()));
    }

    public function test_unit_sauces_for_a_product_not_in_the_order_are_rejected(): void
    {
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 1],
            'sauce_mode' => [$this->salchipapa->id => 'each'],
            'unit_sauces' => [$this->salchipapa->id => [0 => [$this->s('BBQ') => 'APARTE']]],
        ])->assertSessionHasErrors('sauces');

        $this->assertSame(0, Order::count());
    }

    // 4. Salsas generales

    public function test_general_sauces_stay_independent_from_personalized_units(): void
    {
        $this->create(OrderType::DELIVERY, ['items' => [$this->perro->id => 3], 'general_sauces' => [$this->s('Tomate'), $this->s('Chimichurri')]] + $this->threeDifferentPerros())
            ->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $this->assertCount(3, $this->lines($order));
        $this->assertSame(['Tomate', 'Chimichurri'], $order->generalSauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all());
        $this->assertSame(0, $order->generalSauces()->where('placement', '!=', 'APARTE')->count());
    }

    // 5. Adiciones con configuraciones diferentes

    public function test_addition_with_different_configurations_creates_separate_lines_in_the_new_round(): void
    {
        $this->create(OrderType::DELIVERY, ['items' => [$this->salchipapa->id => 1]])->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), [
            'items' => [$this->perro->id => 3, $this->granizado->id => 2],
            'general_sauces' => [$this->s('Mayonesa')],
        ] + $this->threeDifferentPerros())->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame([
            ['Perro caliente', 1, 12000, 'Rosada (EN_PRODUCTO), Tártara (APARTE)'],
            ['Perro caliente', 1, 12000, 'Mostaza (EN_PRODUCTO)'],
            ['Perro caliente', 1, 12000, 'Piña (APARTE), BBQ (EN_PRODUCTO)'],
            ['Granizada de Mora', 2, 16000, ''],
        ], $this->lines($order, 2));
        $this->assertSame(15000 + 36000 + 16000, (int) $order->subtotal);
        $this->assertSame(2 * 1500, (int) $order->packaging_fee, 'Icopor solo por los 2 granizados nuevos.');
        $this->assertSame(15000 + 36000 + 16000 + 3000 + 3000, (int) $order->total);
    }

    // 6. MESA rechaza salsas

    public function test_table_orders_reject_personalized_unit_sauces(): void
    {
        $this->create(OrderType::TABLE, ['items' => [$this->perro->id => 3]] + $this->threeDifferentPerros())->assertSessionHasErrors('sauces');
        $this->assertSame(0, Order::count());

        // En MESA, "personalizar" sin salsas no divide nada: una sola línea ×3.
        $this->create(OrderType::TABLE, ['items' => [$this->perro->id => 3], 'sauce_mode' => [$this->perro->id => 'each']])->assertSessionHasNoErrors();
        $this->assertSame([['Perro caliente', 3, 36000, '']], $this->lines($this->lastOrder()));
        $this->assertSame(0, OrderSauce::count());
    }

    // 7. Impresión y reimpresión

    public function test_print_and_reprint_show_each_unit_line_with_its_sauces(): void
    {
        $this->create(OrderType::TAKEAWAY, ['items' => [$this->perro->id => 3], 'general_sauces' => [$this->s('Tomate')]] + $this->threeDifferentPerros())
            ->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $printed = $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk()->getContent();
        $before = DB::table('order_sauces')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $reprinted = $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk()->getContent();

        foreach ([$printed, $reprinted] as $html) {
            // Comanda de cocina y "Pedido completo": tres líneas "1 × Perro caliente", cada una con lo suyo.
            $this->assertSame(6, substr_count($html, '1 × Perro caliente'));
            $this->assertSame(2, substr_count($html, 'Salsas en producto: Rosada'));
            $this->assertSame(2, substr_count($html, 'Salsas aparte: Tártara'));
            $this->assertSame(2, substr_count($html, 'Salsas en producto: Mostaza'));
            $this->assertSame(2, substr_count($html, 'Salsas en producto: BBQ'));
            $this->assertSame(2, substr_count($html, 'Salsas aparte: Piña'));
            $this->assertSame(2, substr_count($html, 'Salsas generales (aparte)'));

            $first = strpos($html, 'Salsas en producto: Rosada');
            $second = strpos($html, 'Salsas en producto: Mostaza');
            $third = strpos($html, 'Salsas en producto: BBQ');
            $this->assertTrue($first < $second && $second < $third, 'Cada unidad conserva su orden.');
        }

        $this->assertSame($before, DB::table('order_sauces')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
    }

    // Porciones divididas por unidad siguen acompañando al producto elegido

    public function test_personalized_portion_lines_all_pair_with_the_chosen_product(): void
    {
        $this->create(OrderType::TAKEAWAY, [
            'items' => [$this->perro->id => 1, $this->porcion->id => 2],
            'portion_pairing' => [$this->porcion->id => $this->perro->id],
            'sauce_mode' => [$this->porcion->id => 'each'],
            'unit_sauces' => [$this->porcion->id => [0 => [$this->s('Tomate') => 'APARTE'], 1 => [$this->s('Mayonesa') => 'APARTE']]],
        ])->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $perroLine = OrderItem::where('order_id', $order->id)->where('product_id', $this->perro->id)->value('id');
        $portionLines = OrderItem::where('order_id', $order->id)->where('product_id', $this->porcion->id)->get();
        $this->assertCount(2, $portionLines);
        $this->assertSame([$perroLine, $perroLine], $portionLines->pluck('paired_order_item_id')->all());
    }

    // Rendimiento: las 17 salsas se escriben una sola vez por página

    public function test_order_forms_render_the_sauce_options_once(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.orders.create'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="sauce-options-template"'));
        $this->assertSame(17, substr_count($html, 'name="__NAME__['));
        $this->assertSame(0, substr_count($html, 'name="sauces['));
        $this->assertSame(Product::where('allows_sauces', true)->count(), substr_count($html, 'data-sauce-picker data-product-id'));
    }
}

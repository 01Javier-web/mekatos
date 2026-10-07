<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Icopor ($1.500 por unidad) para jugos, bebidas preparadas y granizados en
 * PARA_LLEVAR y DOMICILIO; nunca en MESA ni para otros productos. Se comprueba
 * sobre pedidos reales: packaging_fee y total guardados.
 */
class PackagingFeeTest extends TestCase
{
    use RefreshDatabase;

    private const ICOPOR = 1500;

    private const DELIVERY_FEE = 3000;

    private User $admin;

    private Product $granizado;

    private Product $jugo;

    private Product $preparada;

    private Product $plato;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->granizado = $this->product('Granizada de Mora', 'Granizadas', 8000);
        $this->jugo = $this->product('Jugo Natural Jarra', 'Jugos y Bebidas Preparadas', 8500);
        $this->preparada = $this->product('Limonada Jarra', 'Jugos y Bebidas Preparadas', 7000);
        $this->plato = $this->product('Plato del día', 'Platos', 20000);
    }

    private function product(string $name, string $category, int $price): Product
    {
        $category = Category::firstOrCreate(['name' => $category], ['description' => null, 'sort_order' => 1, 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => $name, 'description' => null, 'price' => $price, 'is_available' => true]);
    }

    /**
     * Crea un pedido desde el formulario web. $items: [product_id => cantidad].
     */
    private function webOrder(OrderType $type, array $items): Order
    {
        $data = ['type' => $type->value, 'items' => $items];

        if (isset($items[$this->jugo->id])) {
            $data['juice_preparation'] = [$this->jugo->id => 'AGUA'];
            $data['juice_fruit'] = [$this->jugo->id => 'MARACUYA'];
        }
        if ($type === OrderType::TABLE) {
            $data['table_id'] = RestaurantTable::create(['number' => 1, 'capacity' => 4, 'qr_token' => 'mesa-1', 'status' => TableStatus::AVAILABLE])->id;
        }
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => (string) self::DELIVERY_FEE];
        }

        $this->actingAs($this->admin)->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function addition(Order $order, array $items): Order
    {
        $data = ['items' => $items];
        if (isset($items[$this->jugo->id])) {
            $data['juice_preparation'] = [$this->jugo->id => 'AGUA'];
            $data['juice_fruit'] = [$this->jugo->id => 'MARACUYA'];
        }

        $this->actingAs($this->admin)->post(route('admin.orders.add.store', $order), $data)->assertSessionHasNoErrors();

        return $order->fresh();
    }

    private function assertTotals(Order $order, int $subtotal, int $packaging, int $deliveryFee): void
    {
        $this->assertSame($subtotal, (int) $order->subtotal, 'subtotal');
        $this->assertSame($packaging, (int) $order->packaging_fee, 'icopor');
        $this->assertSame($deliveryFee, (int) $order->delivery_fee, 'domicilio');
        $this->assertSame($subtotal + $packaging + $deliveryFee, (int) $order->total, 'total = subtotal + icopor + domicilio');
    }

    // --- Cada producto con icopor, en PARA_LLEVAR y DOMICILIO ---

    public function test_each_packaged_product_charges_1500_in_takeaway_and_delivery(): void
    {
        foreach ([$this->granizado, $this->jugo, $this->preparada] as $product) {
            foreach ([OrderType::TAKEAWAY, OrderType::DELIVERY] as $type) {
                $order = $this->webOrder($type, [$product->id => 1]);
                $price = (int) $order->orderItems()->value('unit_price');

                $this->assertTotals($order, $price, self::ICOPOR, $type === OrderType::DELIVERY ? self::DELIVERY_FEE : 0);
            }
        }
    }

    public function test_normal_products_never_charge_packaging(): void
    {
        $takeaway = $this->webOrder(OrderType::TAKEAWAY, [$this->plato->id => 2]);
        $delivery = $this->webOrder(OrderType::DELIVERY, [$this->plato->id => 2]);

        $this->assertTotals($takeaway, 40000, 0, 0);
        $this->assertTotals($delivery, 40000, 0, self::DELIVERY_FEE);
    }

    public function test_table_orders_never_charge_packaging(): void
    {
        $order = $this->webOrder(OrderType::TABLE, [$this->granizado->id => 2, $this->jugo->id => 1, $this->preparada->id => 1, $this->plato->id => 1]);

        $this->assertTotals($order, 2 * 8000 + 8500 + 7000 + 20000, 0, 0);
    }

    // --- Varias unidades y combinaciones ---

    public function test_packaging_is_charged_per_unit_and_only_for_packaged_products(): void
    {
        // 3 granizados + 2 jugos + 1 limonada = 6 unidades con icopor; 2 platos sin icopor.
        $items = [$this->granizado->id => 3, $this->jugo->id => 2, $this->preparada->id => 1, $this->plato->id => 2];
        $subtotal = 3 * 8000 + 2 * 8500 + 7000 + 2 * 20000;

        $takeaway = $this->webOrder(OrderType::TAKEAWAY, $items);
        $delivery = $this->webOrder(OrderType::DELIVERY, $items);

        $this->assertTotals($takeaway, $subtotal, 6 * self::ICOPOR, 0);
        $this->assertTotals($delivery, $subtotal, 6 * self::ICOPOR, self::DELIVERY_FEE);
    }

    public function test_api_delivery_order_charges_the_same_packaging(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/orders', [
            'type' => 'DOMICILIO',
            'customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => (string) self::DELIVERY_FEE,
            'items' => [
                ['product_id' => $this->granizado->id, 'quantity' => 2],
                ['product_id' => $this->plato->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $this->assertTotals(Order::query()->latest('id')->firstOrFail(), 2 * 8000 + 20000, 2 * self::ICOPOR, self::DELIVERY_FEE);
    }

    // --- Adiciones a domicilios: sin duplicar el icopor ---

    public function test_delivery_additions_charge_packaging_once_per_unit_without_duplicates(): void
    {
        $order = $this->webOrder(OrderType::DELIVERY, [$this->granizado->id => 1]);
        $this->assertTotals($order, 8000, 1 * self::ICOPOR, self::DELIVERY_FEE);

        // Primera adición: 1 jugo (con icopor) + 1 plato (sin icopor).
        $order = $this->addition($order, [$this->jugo->id => 1, $this->plato->id => 1]);
        $this->assertTotals($order, 8000 + 8500 + 20000, 2 * self::ICOPOR, self::DELIVERY_FEE);

        // Segunda adición: 2 limonadas. El granizado y el jugo no se vuelven a cobrar.
        $order = $this->addition($order, [$this->preparada->id => 2]);
        $this->assertTotals($order, 8000 + 8500 + 20000 + 2 * 7000, 4 * self::ICOPOR, self::DELIVERY_FEE);

        // Una adición sin productos con icopor no cambia el icopor.
        $order = $this->addition($order, [$this->plato->id => 1]);
        $this->assertTotals($order, 8000 + 8500 + 20000 + 2 * 7000 + 20000, 4 * self::ICOPOR, self::DELIVERY_FEE);
    }

    public function test_takeaway_additions_keep_working_the_same(): void
    {
        $order = $this->webOrder(OrderType::TAKEAWAY, [$this->jugo->id => 1]);
        $order = $this->addition($order, [$this->granizado->id => 2]);

        $this->assertTotals($order, 8500 + 2 * 8000, 3 * self::ICOPOR, 0);
    }

    public function test_table_additions_never_charge_packaging(): void
    {
        $order = $this->webOrder(OrderType::TABLE, [$this->plato->id => 1]);
        $order = $this->addition($order, [$this->granizado->id => 2, $this->jugo->id => 1]);

        $this->assertTotals($order, 20000 + 2 * 8000 + 8500, 0, 0);
    }

    // --- Adiciones: solo se suma el icopor de lo agregado ---

    public function test_old_delivery_keeps_its_historical_packaging_and_only_new_items_are_charged(): void
    {
        // Domicilio creado antes de la regla: tenía un granizado y se guardó sin icopor.
        $order = $this->webOrder(OrderType::DELIVERY, [$this->granizado->id => 1]);
        $order->forceFill(['packaging_fee' => 0, 'total' => 8000 + self::DELIVERY_FEE])->save();
        $this->assertTotals($order->fresh(), 8000, 0, self::DELIVERY_FEE);

        // Nueva adición: 1 granizado. Solo se cobra el icopor de ese granizado.
        $order = $this->addition($order, [$this->granizado->id => 1]);

        $this->assertTotals($order, 2 * 8000, 1 * self::ICOPOR, self::DELIVERY_FEE);
    }

    public function test_addition_with_only_packaged_products_adds_their_packaging(): void
    {
        $order = $this->webOrder(OrderType::DELIVERY, [$this->plato->id => 1]);
        $this->assertTotals($order, 20000, 0, self::DELIVERY_FEE);

        $order = $this->addition($order, [$this->granizado->id => 2, $this->jugo->id => 1]);

        $this->assertTotals($order, 20000 + 2 * 8000 + 8500, 3 * self::ICOPOR, self::DELIVERY_FEE);
    }

    public function test_addition_without_packaged_products_keeps_the_packaging_unchanged(): void
    {
        $order = $this->webOrder(OrderType::DELIVERY, [$this->preparada->id => 2]);
        $this->assertTotals($order, 2 * 7000, 2 * self::ICOPOR, self::DELIVERY_FEE);

        $order = $this->addition($order, [$this->plato->id => 3]);

        $this->assertTotals($order, 2 * 7000 + 3 * 20000, 2 * self::ICOPOR, self::DELIVERY_FEE);
    }

    public function test_addition_mixing_products_with_and_without_packaging(): void
    {
        $order = $this->webOrder(OrderType::DELIVERY, [$this->jugo->id => 1, $this->plato->id => 1]);
        $this->assertTotals($order, 8500 + 20000, 1 * self::ICOPOR, self::DELIVERY_FEE);

        $order = $this->addition($order, [$this->granizado->id => 1, $this->preparada->id => 2, $this->plato->id => 2]);

        $this->assertTotals($order, 8500 + 20000 + 8000 + 2 * 7000 + 2 * 20000, 4 * self::ICOPOR, self::DELIVERY_FEE);
    }

    public function test_packaging_is_never_duplicated_across_many_additions(): void
    {
        $order = $this->webOrder(OrderType::DELIVERY, [$this->granizado->id => 1]);
        $units = 1;
        $subtotal = 8000;

        foreach (range(1, 4) as $round) {
            $order = $this->addition($order, [$this->granizado->id => 1, $this->plato->id => 1]);
            $units++;
            $subtotal += 8000 + 20000;

            // Siempre $1.500 por cada unidad con icopor del pedido, ni más ni menos.
            $this->assertTotals($order, $subtotal, $units * self::ICOPOR, self::DELIVERY_FEE);
        }

        $this->assertSame(5, (int) $order->orderItems()->where('product_id', $this->granizado->id)->sum('quantity'));
    }

    // --- Visualización del icopor en DOMICILIO ---

    public function test_delivery_packaging_is_shown_on_order_detail_waiter_screen_and_comanda(): void
    {
        $order = $this->webOrder(OrderType::DELIVERY, [$this->granizado->id => 2]);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('Empaque para llevar')->assertSee('$3.000');
        $this->actingAs($this->admin)->get(route('waiter.orders'))
            ->assertOk()->assertSee('Icopores / empaque para llevar')->assertSee('+$3.000');
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))
            ->assertOk()->assertSee('EMPAQUES')->assertSee('3.000');
    }

    public function test_new_order_and_addition_summaries_include_delivery_in_the_packaging_line(): void
    {
        // El resumen se arma en el navegador: se comprueba que la línea "Empaques para llevar"
        // se calcule y se muestre también para DOMICILIO, y que el granizado esté marcado con icopor.
        $order = $this->webOrder(OrderType::DELIVERY, [$this->plato->id => 1]);

        $add = $this->actingAs($this->admin)->get(route('admin.orders.edit', $order))->assertOk();
        $add->assertSee("const delivery=type==='DOMICILIO'", false)
            ->assertSee("fee=(takeaway||delivery)&&c.dataset.packaging==='1'?1500*q:0", false)
            ->assertSee("const feeLine=(takeaway||delivery)&&packaging?", false)
            ->assertSee('Empaques para llevar');
        $this->assertMatchesRegularExpression('/data-name="granizada de mora"[^>]*data-packaging="1"/', $add->getContent());

        $create = $this->actingAs($this->admin)->get(route('admin.orders.create'))->assertOk();
        $create->assertSee("const fee=(takeaway||delivery)&&c.dataset.packaging==='1'?1500*q:0", false)
            ->assertSee("const feeLine=(takeaway||delivery)&&packaging?", false);
    }
}

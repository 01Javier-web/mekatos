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
 * Salsas para PARA_LLEVAR y DOMICILIO: por producto (EN EL PRODUCTO / APARTE) y
 * generales (siempre aparte). Gratuitas, sin cantidades, nunca se borran.
 */
class SaucesTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICIAL = [
        'Tomate', 'Rosada', 'Tártara', 'Aderezo', 'Española', 'Repollo', 'Mostaza', 'Piña',
        'Piña casera', 'Piña sobre', 'Mayonesa', 'BBQ', 'Maíz', 'Cebolla', 'Ripio de papa',
        'Miel', 'Chimichurri',
    ];

    private User $admin;

    private User $waiter;

    private Product $perro;

    private Product $salchipapas;

    private Product $granizado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $perros = Category::create(['name' => 'Perros', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $salchis = Category::create(['name' => 'Salchipapas', 'description' => null, 'sort_order' => 2, 'is_active' => true]);
        $granizadas = Category::create(['name' => 'Granizadas', 'description' => null, 'sort_order' => 3, 'is_active' => true]);
        $this->perro = Product::create(['category_id' => $perros->id, 'name' => 'Perro Sencillo', 'description' => null, 'price' => 12000, 'is_available' => true]);
        $this->salchipapas = Product::create(['category_id' => $salchis->id, 'name' => 'Salchipapa Sencilla', 'description' => null, 'price' => 15000, 'is_available' => true]);
        $this->granizado = Product::create(['category_id' => $granizadas->id, 'name' => 'Granizada de Mora', 'description' => null, 'price' => 8000, 'is_available' => true]);
    }

    private function sauce(string $name): Sauce
    {
        return Sauce::where('name', $name)->firstOrFail();
    }

    private function orderData(OrderType $type, array $extra = []): array
    {
        $data = ['type' => $type->value, 'items' => [$this->perro->id => 1, $this->salchipapas->id => 1]];
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }
        if ($type === OrderType::TABLE) {
            $data['table_id'] = RestaurantTable::firstOrCreate(['qr_token' => 'mesa-1'], ['number' => 1, 'capacity' => 4, 'status' => TableStatus::AVAILABLE])->id;
        }

        return array_merge($data, $extra);
    }

    /** Perro: Rosada en el producto + Tártara aparte; Salchipapas: BBQ en el producto; generales: Tomate y Chimichurri. */
    private function saucesPayload(): array
    {
        return [
            'sauces' => [
                $this->perro->id => [$this->sauce('Rosada')->id => 'EN_PRODUCTO', $this->sauce('Tártara')->id => 'APARTE', $this->sauce('Mostaza')->id => ''],
                $this->salchipapas->id => [$this->sauce('BBQ')->id => 'EN_PRODUCTO'],
            ],
            'general_sauces' => [$this->sauce('Tomate')->id, $this->sauce('Chimichurri')->id],
        ];
    }

    private function create(OrderType $type, array $extra = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->admin)->post(route('admin.orders.store'), $this->orderData($type, $extra));
    }

    private function lastOrder(): Order
    {
        return Order::query()->latest('id')->firstOrFail();
    }

    private function savedSauces(Order $order): array
    {
        return OrderSauce::query()->with(['sauce', 'orderItem.product'])->where('order_id', $order->id)->orderBy('id')->get()
            ->map(fn (OrderSauce $s) => [$s->orderItem?->product?->name ?? 'GENERAL', $s->sauce->name, $s->placement])->all();
    }

    // --- Catálogo ---

    public function test_the_17_official_sauces_exist_active_and_in_order(): void
    {
        $this->assertSame(self::OFFICIAL, Sauce::query()->orderBy('sort_order')->pluck('name')->all());
        $this->assertSame(17, Sauce::where('is_active', true)->count());
    }

    public function test_admin_can_deactivate_and_reactivate_a_sauce_without_deleting_it(): void
    {
        $rosada = $this->sauce('Rosada');

        $this->actingAs($this->admin)->get(route('admin.sauces.index'))->assertOk()->assertSee('Rosada')->assertSee('Chimichurri');
        $this->actingAs($this->admin)->get(route('admin.settings'))->assertOk()->assertSee(route('admin.sauces.index'), false);

        $this->actingAs($this->admin)->put(route('admin.sauces.toggle', $rosada))->assertRedirect(route('admin.sauces.index'));
        $this->assertFalse($rosada->fresh()->is_active);
        $this->actingAs($this->admin)->put(route('admin.sauces.toggle', $rosada))->assertRedirect(route('admin.sauces.index'));
        $this->assertTrue($rosada->fresh()->is_active);

        $this->assertSame(17, Sauce::count(), 'No se elimina ninguna salsa.');
        // No existe ninguna ruta para eliminar salsas.
        $this->actingAs($this->admin)->delete('/admin/sauces/'.$rosada->id)->assertNotFound();
        $this->assertModelExists($rosada);
    }

    public function test_waiter_cannot_manage_sauces(): void
    {
        $rosada = $this->sauce('Rosada');

        $this->actingAs($this->waiter)->get(route('admin.sauces.index'))->assertForbidden();
        $this->actingAs($this->waiter)->put(route('admin.sauces.toggle', $rosada))->assertForbidden();
        $this->assertTrue($rosada->fresh()->is_active);
    }

    // --- PARA_LLEVAR y DOMICILIO ---

    public function test_takeaway_and_delivery_save_product_and_general_sauces(): void
    {
        foreach ([OrderType::TAKEAWAY, OrderType::DELIVERY] as $type) {
            $this->create($type, $this->saucesPayload())->assertSessionHasNoErrors();
            $order = $this->lastOrder();

            $this->assertSame([
                ['Perro Sencillo', 'Rosada', 'EN_PRODUCTO'],
                ['Perro Sencillo', 'Tártara', 'APARTE'],
                ['Salchipapa Sencilla', 'BBQ', 'EN_PRODUCTO'],
                ['GENERAL', 'Tomate', 'APARTE'],
                ['GENERAL', 'Chimichurri', 'APARTE'],
            ], $this->savedSauces($order), $type->value);

            // Todas quedan en la ronda 1 del pedido.
            $this->assertSame([$order->rounds()->value('id')], OrderSauce::where('order_id', $order->id)->distinct()->pluck('order_round_id')->all());
        }
    }

    public function test_waiter_can_also_add_sauces(): void
    {
        $this->create(OrderType::TAKEAWAY, $this->saucesPayload(), $this->waiter)->assertSessionHasNoErrors();

        $this->assertSame(5, OrderSauce::where('order_id', $this->lastOrder()->id)->count());
    }

    public function test_general_sauces_work_without_product_sauces(): void
    {
        $this->create(OrderType::DELIVERY, ['general_sauces' => [$this->sauce('Miel')->id]])->assertSessionHasNoErrors();

        $this->assertSame([['GENERAL', 'Miel', 'APARTE']], $this->savedSauces($this->lastOrder()));
    }

    public function test_sauces_have_no_quantities_and_cannot_repeat(): void
    {
        $this->assertFalse(Schema::hasColumn('order_sauces', 'quantity'));

        $tomate = $this->sauce('Tomate')->id;
        $this->create(OrderType::TAKEAWAY, ['general_sauces' => [$tomate, $tomate]])->assertSessionHasErrors('general_sauces.0');
        $this->assertSame(0, Order::count());

        $this->create(OrderType::TAKEAWAY, ['sauces' => [$this->perro->id => [$tomate => 'DOS_VECES']]])->assertSessionHasErrors();
        $this->assertSame(0, Order::count());
    }

    public function test_sauces_for_a_product_not_in_the_order_are_rejected(): void
    {
        $this->create(OrderType::TAKEAWAY, ['sauces' => [$this->granizado->id => [$this->sauce('BBQ')->id => 'APARTE']]])
            ->assertSessionHasErrors('sauces');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderSauce::count());
    }

    // --- MESA y QR no permiten salsas ---

    public function test_table_orders_do_not_allow_sauces(): void
    {
        $this->create(OrderType::TABLE, $this->saucesPayload())->assertSessionHasErrors('sauces');
        $this->assertSame(0, Order::count());

        $this->create(OrderType::TABLE, ['general_sauces' => [$this->sauce('Tomate')->id]])->assertSessionHasErrors('sauces');
        $this->assertSame(0, Order::count());

        // Sin salsas, la mesa funciona igual que siempre.
        $this->create(OrderType::TABLE)->assertSessionHasNoErrors();
        $table = $this->lastOrder();
        $this->assertSame(0, OrderSauce::count());

        // La adición de una mesa tampoco ofrece ni acepta salsas.
        $this->actingAs($this->admin)->get(route('admin.orders.add', $table))->assertOk()->assertDontSee('Agregar salsas');
        $this->actingAs($this->admin)->post(route('admin.orders.add.store', $table), ['items' => [$this->perro->id => 1], 'general_sauces' => [$this->sauce('Tomate')->id]])
            ->assertSessionHasErrors('sauces');
        $this->assertSame(2, $table->orderItems()->count());
    }

    public function test_qr_orders_do_not_get_sauces(): void
    {
        $table = RestaurantTable::create(['number' => 2, 'capacity' => 4, 'qr_token' => 'qr-2', 'status' => TableStatus::AVAILABLE]);

        $this->get(route('client.table', 'qr-2'))->assertOk()->assertDontSee('Agregar salsas');
        $this->postJson('/api/orders', [
            'type' => 'MESA', 'table_token' => $table->qr_token,
            'items' => [['product_id' => $this->perro->id, 'quantity' => 1]],
            'sauces' => [$this->perro->id => [$this->sauce('Rosada')->id => 'EN_PRODUCTO']],
            'general_sauces' => [$this->sauce('Tomate')->id],
        ])->assertCreated();

        $this->assertSame(0, OrderSauce::count());
    }

    // --- Salsas desactivadas ---

    public function test_inactive_sauce_is_not_offered_or_accepted_in_new_orders(): void
    {
        $rosada = $this->sauce('Rosada');
        $rosada->update(['is_active' => false]);

        // Las opciones por producto salen de la plantilla única de salsas activas.
        $this->actingAs($this->admin)->get(route('admin.orders.create'))->assertOk()
            ->assertSee('Agregar salsas')
            ->assertDontSee('name="__NAME__['.$rosada->id.']"', false)
            ->assertDontSee('name="general_sauces[]" value="'.$rosada->id.'"', false)
            ->assertSee('name="__NAME__['.$this->sauce('BBQ')->id.']"', false);

        $this->create(OrderType::TAKEAWAY, ['general_sauces' => [$rosada->id]])->assertSessionHasErrors('sauces');
        $this->assertSame(0, Order::count());

        // Al reactivarla vuelve a aparecer.
        $rosada->update(['is_active' => true]);
        $this->actingAs($this->admin)->get(route('admin.orders.create'))->assertSee('name="general_sauces[]" value="'.$rosada->id.'"', false);
    }

    public function test_deactivating_a_sauce_keeps_it_in_historical_orders(): void
    {
        $this->create(OrderType::TAKEAWAY, $this->saucesPayload())->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->sauce('Rosada')->update(['is_active' => false]);
        $this->sauce('Tomate')->update(['is_active' => false]);

        $this->assertSame(5, OrderSauce::where('order_id', $order->id)->count());
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Salsas en producto: Rosada')->assertSee('Tomate, Chimichurri');
        $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk()
            ->assertSee('Salsas en producto: Rosada')->assertSee('Tomate, Chimichurri');
    }

    // --- Precio: las salsas son gratuitas ---

    public function test_sauces_do_not_change_subtotal_packaging_or_total(): void
    {
        $extraItems = ['items' => [$this->perro->id => 1, $this->salchipapas->id => 1, $this->granizado->id => 2]];

        $this->create(OrderType::DELIVERY, $extraItems)->assertSessionHasNoErrors();
        $without = $this->lastOrder();
        $this->create(OrderType::DELIVERY, $extraItems + $this->saucesPayload())->assertSessionHasNoErrors();
        $with = $this->lastOrder();

        foreach (['subtotal', 'packaging_fee', 'delivery_fee', 'tax', 'total'] as $field) {
            $this->assertSame((int) $without->{$field}, (int) $with->{$field}, $field);
        }
        // Icopor intacto: 2 granizados × $1.500.
        $this->assertSame(3000, (int) $with->packaging_fee);
        $this->assertSame(12000 + 15000 + 2 * 8000 + 3000 + 3000, (int) $with->total);
    }

    // --- Comanda, reimpresión y consulta ---

    public function test_comanda_shows_sauces_per_product_and_general_sauces(): void
    {
        $this->create(OrderType::DELIVERY, $this->saucesPayload())->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $html = $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk()->getContent();

        // Comanda de cocina y "Pedido completo": cada producto con sus salsas, y las generales aparte.
        $this->assertSame(2, substr_count($html, 'Salsas en producto: Rosada'));
        $this->assertSame(2, substr_count($html, 'Salsas aparte: Tártara'));
        $this->assertSame(2, substr_count($html, 'Salsas en producto: BBQ'));
        $this->assertSame(2, substr_count($html, 'Salsas generales (aparte)'));
        $this->assertStringContainsString('Tomate, Chimichurri', $html);
        // La salsa sin elegir (Mostaza vacía) no aparece.
        $this->assertStringNotContainsString('Mostaza', $html);

        // El orden deja claro a qué producto pertenece cada salsa.
        $perro = strpos($html, 'Perro Sencillo');
        $rosada = strpos($html, 'Salsas en producto: Rosada');
        $salchipapa = strpos($html, 'Salchipapa Sencilla');
        $bbq = strpos($html, 'Salsas en producto: BBQ');
        $this->assertTrue($perro < $rosada && $rosada < $salchipapa && $salchipapa < $bbq, 'Cada salsa sale debajo de su producto.');
    }

    public function test_reprint_shows_the_same_sauces_without_changing_anything(): void
    {
        $this->create(OrderType::TAKEAWAY, $this->saucesPayload())->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $printed = $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->getContent();
        $before = DB::table('order_sauces')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $status = $order->fresh()->status;

        $reprinted = $this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk()->getContent();

        foreach (['Salsas en producto: Rosada', 'Salsas aparte: Tártara', 'Salsas en producto: BBQ', 'Tomate, Chimichurri'] as $text) {
            $this->assertSame(substr_count($printed, $text), substr_count($reprinted, $text), $text);
        }
        $this->assertSame($before, DB::table('order_sauces')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());
        $this->assertSame($status, $order->fresh()->status);
    }

    public function test_order_detail_and_waiter_screen_show_the_sauces(): void
    {
        $this->create(OrderType::TAKEAWAY, $this->saucesPayload())->assertSessionHasNoErrors();
        $order = $this->lastOrder();

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Salsas en producto: Rosada')->assertSee('Salsas aparte: Tártara')->assertSee('Salsas en producto: BBQ')
            ->assertSee('Salsas generales (aparte)')->assertSee('Tomate, Chimichurri');
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertOk()
            ->assertSee('Salsas en producto: Rosada')->assertSee('Salsas generales (aparte)');
    }

    // --- Adiciones ---

    public function test_sauces_work_in_additions_and_the_addition_comanda_only_shows_new_sauces(): void
    {
        $this->create(OrderType::DELIVERY, $this->saucesPayload())->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();

        $this->actingAs($this->admin)->get(route('admin.orders.add', $order))->assertOk()->assertSee('Agregar salsas');
        $this->actingAs($this->waiter)->post(route('admin.orders.add.store', $order), [
            'items' => [$this->granizado->id => 1],
            'sauces' => [$this->granizado->id => [$this->sauce('Miel')->id => 'APARTE']],
            'general_sauces' => [$this->sauce('Mayonesa')->id],
        ])->assertSessionHasNoErrors();

        $roundTwo = $order->rounds()->where('number', 2)->value('id');
        $this->assertSame([['Granizada de Mora', 'Miel', 'APARTE'], ['GENERAL', 'Mayonesa', 'APARTE']],
            collect($this->savedSauces($order))->slice(5)->values()->all());
        $this->assertSame(2, OrderSauce::where('order_round_id', $roundTwo)->count());

        $addition = $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk()->getContent();
        $this->assertStringContainsString('Salsas aparte: Miel', $addition);
        $this->assertStringContainsString('Mayonesa', $addition);
        // Las salsas generales de la primera ronda no se repiten en la comanda de la adición.
        $this->assertStringNotContainsString('Tomate, Chimichurri', $addition);

        // Icopor de la adición: solo el granizado nuevo.
        $this->assertSame(1500, (int) $order->fresh()->packaging_fee);
    }

    // --- Cierre del día ---

    public function test_close_day_removes_order_sauces_but_keeps_the_catalog(): void
    {
        $this->create(OrderType::TAKEAWAY, $this->saucesPayload())->assertSessionHasNoErrors();
        $order = $this->lastOrder();
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk();
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('admin.reports.daily.close'))->assertOk()->assertSessionHasNoErrors();

        $this->assertSame(0, OrderSauce::count());
        $this->assertSame(17, Sauce::count());
    }
}

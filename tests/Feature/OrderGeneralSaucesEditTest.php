<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderSauce;
use App\Models\Product;
use App\Models\Sauce;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Salsas generales en "✏️ Editar pedido": la no enviada se borra; la enviada se anula, exige
 * motivo, vuelve el pedido a PENDIENTE y sale como "❌ NO PREPARAR".
 */
class OrderGeneralSaucesEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $perro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $comidas = Category::create(['name' => 'Comidas', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $this->perro = Product::create(['category_id' => $comidas->id, 'name' => 'Perro Sencillo', 'description' => null, 'price' => 13000, 'is_available' => true, 'allows_sauces' => true]);
    }

    private function s(string $name): int
    {
        return Sauce::where('name', $name)->value('id');
    }

    private function createOrder(array $generalSauces, OrderType $type = OrderType::TAKEAWAY): Order
    {
        $data = ['type' => $type->value, 'items' => [$this->perro->id => 1], 'general_sauces' => array_map(fn ($n) => $this->s($n), $generalSauces)];
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function general(Order $order, string $name): OrderSauce
    {
        return OrderSauce::where('order_id', $order->id)->whereNull('order_item_id')->where('sauce_id', $this->s($name))->latest('id')->firstOrFail();
    }

    private function print(Order $order): string
    {
        return $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk()->getContent();
    }

    private function edit(Order $order, array $data)
    {
        return $this->actingAs($this->waiter)->post(route('admin.orders.update', $order), $data);
    }

    private function kitchen(string $html): string
    {
        foreach (array_slice(explode('<section class="ticket">', $html), 1) as $ticket) {
            if (str_contains($ticket, '<h1>Cocina</h1>')) {
                return $ticket;
            }
        }

        return '';
    }

    /** Bloque "Salsas generales (aparte)" de una pantalla (detalle o mesero). */
    private function generalBlock(string $html): string
    {
        return preg_match('/<strong>Salsas generales \(aparte\)<\/strong>(.*?)<\/div>/s', $html, $m) ? $m[1] : '';
    }

    private function snapshot(): array
    {
        return collect(['orders', 'order_items', 'order_sauces', 'order_status_histories', 'order_rounds'])
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])
            ->all();
    }

    public function test_removing_an_unsent_general_sauce_deletes_it_without_kitchen_notice(): void
    {
        $order = $this->createOrder(['Tomate', 'Mayonesa']);
        $tomate = $this->general($order, 'Tomate');
        $this->actingAs($this->waiter)->get(route('admin.orders.edit', $order))->assertSee('🗑️ Quitar Tomate')->assertSee('name="remove_general_sauces[]" value="'.$tomate->id.'"', false);

        // Sin productos ni motivo: es una edición válida.
        $this->edit($order, ['remove_general_sauces' => [$tomate->id]])->assertSessionHasNoErrors();

        $this->assertModelMissing($tomate);
        $this->assertSame(['Mayonesa'], $order->generalSauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all());
        $this->assertStringContainsString('❌ Salsas generales quitadas: Tomate', (string) $order->statusHistories()->latest('id')->value('notes'));
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);

        $kitchen = $this->kitchen($this->print($order));
        $this->assertStringNotContainsString('NO PREPARAR', $kitchen);
        $this->assertStringContainsString('Mayonesa', $kitchen);
        $this->assertStringNotContainsString('Tomate', $kitchen);
        $this->assertNotNull($this->general($order, 'Mayonesa')->sent_at);
    }

    public function test_removing_a_sent_general_sauce_requires_reason_voids_it_and_prints_no_preparar(): void
    {
        $order = $this->createOrder(['Tomate', 'Mayonesa'], OrderType::DELIVERY);
        $this->print($order);
        $tomate = $this->general($order, 'Tomate');
        $this->assertNotNull($tomate->sent_at, 'Al imprimir se marca como enviada.');
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);

        $this->edit($order, ['remove_general_sauces' => [$tomate->id]])->assertSessionHasErrors('reason');
        $this->assertNull($tomate->fresh()->voided_at);

        $this->edit($order, ['remove_general_sauces' => [$tomate->id], 'reason' => 'No quiere tomate'])->assertSessionHasNoErrors();

        $tomate->refresh();
        $this->assertNotNull($tomate->voided_at, 'Nunca se borra si ya se envió.');
        $this->assertNull($tomate->void_sent_at);
        $order->refresh();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(['POR COBRAR', 'PENDIENTE'], [$order->statusHistories()->latest('id')->value('previous_status'), $order->statusHistories()->latest('id')->value('new_status')]);
        $this->assertStringContainsString('❌ Salsas generales quitadas: Tomate (ya enviada)', (string) $order->statusHistories()->latest('id')->value('notes'));
        $this->assertSame(['Mayonesa'], $order->generalSauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all());

        // Pantallas: solo las vigentes.
        $this->assertSame('Mayonesa', trim(strip_tags($this->generalBlock($this->actingAs($this->admin)->get(route('admin.orders.show', $order))->getContent())), " \n<br>"));
        $this->assertStringNotContainsString('Tomate', $this->generalBlock($this->actingAs($this->waiter)->get(route('waiter.orders'))->getContent()));

        $html = $this->print($order);
        $kitchen = $this->kitchen($html);
        $this->assertStringContainsString('❌ NO PREPARAR', $kitchen);
        $this->assertStringContainsString('Salsas generales (aparte): Tomate', $kitchen);
        $this->assertStringContainsString('Salsas generales (aparte): Tomate', $html, 'También en el pedido actualizado.');
        $this->assertNotNull($tomate->fresh()->void_sent_at);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
        $this->assertSame('Comanda de cambio impresa.', $order->statusHistories()->latest('id')->value('notes'));
    }

    public function test_a_general_sauce_added_in_an_edit_without_products_is_printed(): void
    {
        $order = $this->createOrder(['Tomate']);
        $this->print($order);

        $this->edit($order, ['general_sauces' => [$this->s('Chimichurri')]])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
        $this->assertStringContainsString('✅ Salsas generales agregadas: Chimichurri', (string) $order->statusHistories()->latest('id')->value('notes'));
        $this->actingAs($this->admin)->getJson(route('admin.orders.pending'))->assertJsonPath('ids', [$order->id]);

        $kitchen = $this->kitchen($this->print($order));
        $this->assertStringContainsString('Chimichurri', $kitchen);
        $this->assertStringNotContainsString('Tomate', $kitchen, 'La ya enviada no se repite.');
        $this->assertNotNull($this->general($order, 'Chimichurri')->sent_at);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
    }

    public function test_reprint_keeps_the_general_sauce_information_of_the_last_ticket(): void
    {
        $order = $this->createOrder(['Tomate', 'Mayonesa']);
        $this->print($order);
        $this->travel(2)->minutes();
        $this->edit($order, ['remove_general_sauces' => [$this->general($order, 'Tomate')->id], 'general_sauces' => [$this->s('Chimichurri')], 'reason' => 'Cambio de salsa'])->assertSessionHasNoErrors();
        $this->print($order);
        $before = $this->snapshot();

        foreach ([$this->waiter, $this->admin] as $user) {
            $kitchen = $this->kitchen($this->actingAs($user)->get(route('admin.orders.reprint', $order))->assertOk()->getContent());
            $this->assertStringContainsString('*** REIMPRESIÓN ***', $kitchen);
            $this->assertStringContainsString('Salsas generales (aparte): Tomate', $kitchen);
            $this->assertStringContainsString('Chimichurri', $kitchen);
            $this->assertStringNotContainsString('Mayonesa', $kitchen, 'Solo lo de esa tanda.');
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_cancellation_ticket_includes_general_sauces_already_sent(): void
    {
        $order = $this->createOrder(['Tomate', 'Mayonesa']);
        $this->print($order);
        $this->travel(2)->minutes();
        // Tomate anulada y ya avisada; Chimichurri agregada pero nunca enviada.
        $this->edit($order, ['remove_general_sauces' => [$this->general($order, 'Tomate')->id], 'reason' => 'x'])->assertSessionHasNoErrors();
        $this->print($order);
        $this->edit($order, ['general_sauces' => [$this->s('Chimichurri')]])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->put(route('admin.orders.cancel', $order), ['reason' => 'Se fue'])->assertSessionHasNoErrors();

        $kitchen = $this->kitchen($this->actingAs($this->admin)->get(route('admin.orders.cancellation-ticket', $order))->assertOk()->getContent());
        $this->assertStringContainsString('Salsas generales (aparte): Mayonesa', $kitchen);
        $this->assertStringNotContainsString('Tomate', $kitchen);
        $this->assertStringNotContainsString('Chimichurri', $kitchen);
    }

    public function test_product_sauces_keep_their_behavior(): void
    {
        $this->actingAs($this->admin)->post(route('admin.orders.store'), [
            'type' => 'PARA_LLEVAR', 'items' => [$this->perro->id => 1],
            'sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']],
        ])->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->print($order);

        // Las salsas por producto no reciben marca de envío propia: dependen de su línea.
        $this->assertSame(0, OrderSauce::where('order_id', $order->id)->whereNotNull('order_item_id')->whereNotNull('sent_at')->count());
        $this->assertFalse($order->fresh()->hasPendingKitchenChanges());
    }

    public function test_migration_recovers_sent_at_only_for_general_sauces_of_printed_rounds(): void
    {
        $printed = $this->createOrder(['Tomate']);
        $this->print($printed);
        $notPrinted = $this->createOrder(['Mayonesa']);
        $roundPrinted = $printed->rounds()->value('id');
        $printedAt = (string) DB::table('order_items')->where('order_round_id', $roundPrinted)->value('sent_at');

        // Simula los datos de antes de la migración (sin sent_at).
        DB::table('order_sauces')->update(['sent_at' => null]);

        (require database_path('migrations/2026_10_06_000002_add_void_fields_to_order_items.php'))->up();

        $this->assertSame($printedAt, (string) $this->general($printed, 'Tomate')->sent_at);
        $this->assertNull($this->general($notPrinted, 'Mayonesa')->sent_at);
        $this->assertSame(0, OrderSauce::whereNotNull('order_item_id')->whereNotNull('sent_at')->count());
    }
}

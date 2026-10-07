<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
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
 * Comandas de cambio ("❌ NO PREPARAR" / "✅ PREPARAR"), reimpresión, comanda de cancelación
 * y los dos botones principales: "✏️ Editar pedido" y "❌ Cancelar pedido".
 */
class OrderChangeTicketTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $perro;

    private Product $hamburguesa;

    private Product $salchipapa;

    private Product $granizada;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $comidas = Category::create(['name' => 'Comidas', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $granizadas = Category::create(['name' => 'Granizadas', 'description' => null, 'sort_order' => 2, 'is_active' => true]);
        $this->perro = Product::create(['category_id' => $comidas->id, 'name' => 'Perro Sencillo', 'description' => null, 'price' => 13000, 'is_available' => true, 'allows_sauces' => true]);
        $this->hamburguesa = Product::create(['category_id' => $comidas->id, 'name' => 'Hamburguesa Sencilla', 'description' => null, 'price' => 23500, 'is_available' => true, 'allows_sauces' => true]);
        $this->salchipapa = Product::create(['category_id' => $comidas->id, 'name' => 'Salchipapa', 'description' => null, 'price' => 18500, 'is_available' => true, 'allows_sauces' => true]);
        $this->granizada = Product::create(['category_id' => $granizadas->id, 'name' => 'Granizada de Mora', 'description' => null, 'price' => 8500, 'is_available' => true]);
        $this->table = RestaurantTable::create(['number' => 6, 'capacity' => 4, 'qr_token' => 'mesa-6', 'status' => TableStatus::AVAILABLE]);
    }

    private function s(string $name): int
    {
        return Sauce::where('name', $name)->value('id');
    }

    private function createOrder(OrderType $type, array $items, array $extra = []): Order
    {
        $data = ['type' => $type->value, 'items' => $items] + $extra;
        if ($type === OrderType::TABLE) {
            $data['table_id'] = $this->table->id;
        }
        if ($type === OrderType::DELIVERY) {
            $data += ['customer_name' => 'Cliente', 'customer_phone' => '3000000000', 'delivery_address' => 'Calle 1', 'delivery_fee' => '3000'];
        }
        $this->actingAs($this->admin)->post(route('admin.orders.store'), $data)->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function print(Order $order): string
    {
        return $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertOk()->getContent();
    }

    private function edit(Order $order, array $data)
    {
        return $this->actingAs($this->waiter)->post(route('admin.orders.update', $order), $data);
    }

    private function line(Order $order, Product $product): OrderItem
    {
        return OrderItem::where('order_id', $order->id)->where('product_id', $product->id)->whereNull('voided_at')->orderBy('id')->firstOrFail();
    }

    /** @return array<string, string> título del ticket => html del ticket */
    private function tickets(string $html): array
    {
        $tickets = [];
        foreach (array_slice(explode('<section class="ticket">', $html), 1) as $ticket) {
            preg_match('/<h1>(.*?)<\/h1>/s', $ticket, $m);
            $tickets[trim($m[1] ?? '')] = $ticket;
        }

        return $tickets;
    }

    private function snapshot(): array
    {
        return collect(['orders', 'order_items', 'order_rounds', 'order_status_histories', 'order_sauces', 'table_sessions'])
            ->mapWithKeys(fn (string $t) => [$t => DB::table($t)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all()])
            ->all();
    }

    public function test_substitution_prints_no_preparar_then_preparar_with_the_same_order_number(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->hamburguesa->id => 1, $this->perro->id => 2]);
        $this->print($order);
        $this->edit($order, ['void' => [$this->line($order, $this->hamburguesa)->id => 1], 'items' => [$this->salchipapa->id => 1], 'reason' => 'Cambio'])->assertSessionHasNoErrors();

        $html = $this->print($order);
        $kitchen = $this->tickets($html)['Cocina'];
        $this->assertStringContainsString('CAMBIO · PEDIDO #'.$order->id.' · RONDA 2', $kitchen);
        $no = strpos($kitchen, '❌ NO PREPARAR');
        $yes = strpos($kitchen, '✅ PREPARAR');
        $this->assertNotFalse($no);
        $this->assertNotFalse($yes);
        $this->assertTrue($no < strpos($kitchen, '1 × Hamburguesa Sencilla') && strpos($kitchen, '1 × Hamburguesa Sencilla') < $yes && $yes < strpos($kitchen, '1 × Salchipapa'));
        $this->assertStringNotContainsString('Perro Sencillo', $kitchen, 'Lo que no cambió no se vuelve a pedir.');

        // Pedido completo actualizado (para llevar): lo retirado y lo vigente con el nuevo total.
        $full = $this->tickets($html)['Pedido actualizado'];
        $this->assertStringContainsString('CAMBIO · RONDA 2', $full);
        $this->assertStringContainsString('PEDIDO VIGENTE', $full);
        $this->assertStringContainsString('2 × Perro Sencillo', $full);
        $this->assertStringContainsString('1 × Salchipapa', $full);
        $this->assertStringContainsString('&#36;44.500', $full);

        $order->refresh();
        $this->assertSame(1, Order::count());
        $this->assertSame(OrderStatus::TO_COLLECT, $order->status);
        $this->assertNotNull(OrderItem::where('order_id', $order->id)->whereNotNull('voided_at')->value('void_sent_at'));
        $this->assertSame('Comanda de cambio impresa.', $order->statusHistories()->latest('id')->value('notes'));
        $this->assertFalse($order->hasPendingKitchenChanges());
    }

    public function test_change_ticket_is_separated_by_station(): void
    {
        $order = $this->createOrder(OrderType::TABLE, [$this->perro->id => 1, $this->granizada->id => 1]);
        $this->print($order);
        $this->edit($order, ['void' => [$this->line($order, $this->granizada)->id => 1], 'items' => [$this->salchipapa->id => 1], 'reason' => 'Ya no quiere la granizada'])->assertSessionHasNoErrors();

        $tickets = $this->tickets($this->print($order));
        $this->assertStringContainsString('✅ PREPARAR', $tickets['Cocina']);
        $this->assertStringContainsString('1 × Salchipapa', $tickets['Cocina']);
        $this->assertStringNotContainsString('NO PREPARAR', $tickets['Cocina']);
        $this->assertStringContainsString('❌ NO PREPARAR', $tickets['Bebidas']);
        $this->assertStringContainsString('1 × Granizada de Mora', $tickets['Bebidas']);
        $this->assertStringNotContainsString('✅ PREPARAR', $tickets['Bebidas']);
    }

    public function test_change_sauces_prints_the_same_product_as_no_preparar_and_preparar(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1], ['sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']]]);
        $this->print($order);
        $this->edit($order, [
            'void' => [$this->line($order, $this->perro)->id => 1],
            'items' => [$this->perro->id => 1],
            'sauces' => [$this->perro->id => [$this->s('Tártara') => 'APARTE']],
            'reason' => 'Otra salsa',
        ])->assertSessionHasNoErrors();

        $kitchen = $this->tickets($this->print($order))['Cocina'];
        $no = strpos($kitchen, '❌ NO PREPARAR');
        $yes = strpos($kitchen, '✅ PREPARAR');
        $rosada = strpos($kitchen, 'Salsas en producto: Rosada');
        $tartara = strpos($kitchen, 'Salsas aparte: Tártara');
        $this->assertTrue($no < $rosada && $rosada < $yes && $yes < $tartara);
        $this->assertSame(2, substr_count($kitchen, '1 × Perro Sencillo'));
    }

    public function test_only_removing_sent_items_prints_a_no_preparar_ticket(): void
    {
        $order = $this->createOrder(OrderType::DELIVERY, [$this->perro->id => 2, $this->salchipapa->id => 1]);
        $this->print($order);
        $this->edit($order, ['void' => [$this->line($order, $this->perro)->id => 1], 'reason' => 'Solo uno'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);

        $kitchen = $this->tickets($this->print($order))['Cocina'];
        $this->assertStringContainsString('❌ NO PREPARAR', $kitchen);
        $this->assertStringContainsString('1 × Perro Sencillo', $kitchen);
        $this->assertStringNotContainsString('✅ PREPARAR', $kitchen);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
    }

    public function test_removing_an_unsent_addition_needs_no_notice_and_returns_to_por_cobrar(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);
        $this->print($order);
        $this->edit($order, ['items' => [$this->salchipapa->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);

        // Se quita la adición antes de imprimirla: no hay nada que avisar a cocina.
        $this->edit($order, ['void' => [$this->line($order, $this->salchipapa)->id => 1]])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(OrderStatus::TO_COLLECT, $order->status);
        $this->assertFalse($order->hasPendingKitchenChanges());
        $this->actingAs($this->admin)->get(route('admin.orders.print', $order))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->getJson(route('admin.orders.pending'))->assertJsonPath('count', 0);
    }

    public function test_reprint_reproduces_the_last_change_ticket_without_changing_anything(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->hamburguesa->id => 1, $this->perro->id => 1]);
        $this->print($order);
        // Cada impresión se identifica por su segundo (sent_at): la de cambio ocurre después.
        $this->travel(2)->minutes();
        $this->edit($order, ['void' => [$this->line($order, $this->hamburguesa)->id => 1], 'items' => [$this->salchipapa->id => 1], 'reason' => 'Cambio'])->assertSessionHasNoErrors();
        $this->print($order);
        $before = $this->snapshot();

        foreach ([$this->waiter, $this->admin] as $user) {
            $tickets = $this->tickets($this->actingAs($user)->get(route('admin.orders.reprint', $order))->assertOk()->getContent());
            $kitchen = $tickets['Cocina'];
            $this->assertStringContainsString('*** REIMPRESIÓN ***', $kitchen);
            $this->assertStringContainsString('❌ NO PREPARAR', $kitchen);
            $this->assertStringContainsString('1 × Hamburguesa Sencilla', $kitchen);
            $this->assertStringContainsString('✅ PREPARAR', $kitchen);
            $this->assertStringContainsString('1 × Salchipapa', $kitchen);
            $this->assertStringNotContainsString('Perro Sencillo', $kitchen);
            $this->assertStringContainsString('PEDIDO VIGENTE', $tickets['Pedido actualizado']);
        }
        $this->assertSame($before, $this->snapshot());
    }

    public function test_reprint_of_a_normal_ticket_never_asks_to_prepare_a_voided_line(): void
    {
        $order = $this->createOrder(OrderType::TABLE, [$this->hamburguesa->id => 1, $this->perro->id => 1]);
        $this->print($order);
        // Se quita la hamburguesa, pero el cambio todavía no se imprime.
        $this->edit($order, ['void' => [$this->line($order, $this->hamburguesa)->id => 1], 'reason' => 'Cambio'])->assertSessionHasNoErrors();

        $kitchen = $this->tickets($this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk()->getContent())['Cocina'];
        $this->assertStringContainsString('1 × Perro Sencillo', $kitchen);
        $this->assertStringNotContainsString('Hamburguesa', $kitchen);
    }

    public function test_cancellation_ticket_is_generated_by_admin_and_only_reprinted_by_waiter(): void
    {
        $order = $this->createOrder(OrderType::TABLE, [$this->perro->id => 2, $this->granizada->id => 1], ['sauces' => []]);
        $this->print($order);

        // El mesero cancela: se le avisa que caja debe imprimir la comanda (sin enlace para generarla).
        $this->actingAs($this->waiter)->put(route('admin.orders.cancel', $order), ['reason' => 'El cliente se fue'])
            ->assertRedirect(route('waiter.orders'))
            ->assertSessionMissing('cancel_ticket')
            ->assertSessionHas('cancel_ticket_notice');
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertSee('caja debe imprimir la comanda de cancelación')
            ->assertDontSee(route('admin.orders.cancellation-ticket', $order), false);

        // El mesero no puede generarla ni reimprimir algo que caja todavía no imprimió.
        $before = $this->snapshot();
        $this->actingAs($this->waiter)->get(route('admin.orders.cancellation-ticket', $order))->assertForbidden();
        $this->actingAs($this->waiter)->get(route('admin.orders.cancellation-ticket.reprint', $order))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->get(route('admin.orders.cancellation-ticket.reprint', $order))->assertSessionHasErrors('status');
        $this->assertSame($before, $this->snapshot());
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertSee('🖨️ Imprimir comanda de cancelación')->assertDontSee('🔁 Reimprimir comanda de cancelación');

        // ADMIN la genera: queda registrada una sola vez en el historial, sin cambiar el estado.
        $tickets = $this->tickets($this->actingAs($this->admin)->get(route('admin.orders.cancellation-ticket', $order))->assertOk()->getContent());
        $this->assertStringContainsString('❌ PEDIDO #'.$order->id.' CANCELADO — NO PREPARAR', $tickets['Cocina']);
        $this->assertStringContainsString('2 × Perro Sencillo', $tickets['Cocina']);
        $this->assertStringContainsString('El cliente se fue', $tickets['Cocina']);
        $this->assertStringContainsString('1 × Granizada de Mora', $tickets['Bebidas']);
        $this->assertStringNotContainsString('REIMPRESIÓN', $tickets['Cocina']);
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(1, $order->statusHistories()->where('notes', 'Comanda de cancelación impresa.')->count());
        $after = $this->snapshot();

        // Reimpresiones: ADMIN (por cualquiera de las dos rutas) y MESERO; solo lectura.
        foreach ([[$this->admin, 'admin.orders.cancellation-ticket'], [$this->admin, 'admin.orders.cancellation-ticket.reprint'], [$this->waiter, 'admin.orders.cancellation-ticket.reprint']] as [$user, $route]) {
            $tickets = $this->tickets($this->actingAs($user)->get(route($route, $order))->assertOk()->getContent());
            $this->assertStringContainsString('*** REIMPRESIÓN ***', $tickets['Cocina'], $route);
            $this->assertStringContainsString('El cliente se fue', $tickets['Cocina'], 'El motivo es el de la cancelación.');
        }
        $this->assertSame($after, $this->snapshot(), 'Reimprimir no cambia nada.');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertSee('🔁 Reimprimir comanda de cancelación');
    }

    public function test_admin_cancelling_gets_the_link_to_generate_the_cancellation_ticket(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);
        $this->print($order);
        $this->actingAs($this->admin)->put(route('admin.orders.cancel', $order), ['reason' => 'Error de caja'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHas('cancel_ticket', route('admin.orders.cancellation-ticket', $order));
    }

    public function test_no_cancellation_ticket_when_the_order_was_never_sent_or_is_not_cancelled(): void
    {
        $never = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);
        $this->actingAs($this->waiter)->put(route('admin.orders.cancel', $never), ['reason' => 'Error'])->assertSessionMissing('cancel_ticket')->assertSessionMissing('cancel_ticket_notice');
        $this->actingAs($this->admin)->get(route('admin.orders.cancellation-ticket', $never))->assertSessionHasErrors('status');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $never))->assertDontSee('Imprimir comanda de cancelación')->assertDontSee('Reimprimir comanda de cancelación');

        $active = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);
        $this->print($active);
        $this->actingAs($this->admin)->get(route('admin.orders.cancellation-ticket', $active))->assertSessionHasErrors('status');
    }

    public function test_cancellation_ticket_includes_voided_sent_lines_not_yet_announced_but_not_announced_ones(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->hamburguesa->id => 1, $this->perro->id => 1, $this->salchipapa->id => 1]);
        $this->print($order);
        // Hamburguesa anulada y ya avisada; salchipapa anulada sin avisar todavía.
        $this->edit($order, ['void' => [$this->line($order, $this->hamburguesa)->id => 1], 'reason' => 'a'])->assertSessionHasNoErrors();
        $this->print($order);
        $this->edit($order, ['void' => [$this->line($order, $this->salchipapa)->id => 1], 'reason' => 'b'])->assertSessionHasNoErrors();
        $this->actingAs($this->waiter)->put(route('admin.orders.cancel', $order), ['reason' => 'Se canceló todo'])->assertSessionHasNoErrors();

        $kitchen = $this->tickets($this->actingAs($this->admin)->get(route('admin.orders.cancellation-ticket', $order))->getContent())['Cocina'];
        $this->assertStringContainsString('1 × Perro Sencillo', $kitchen);
        $this->assertStringContainsString('1 × Salchipapa', $kitchen);
        $this->assertStringNotContainsString('Hamburguesa', $kitchen);
    }

    public function test_main_actions_are_editar_and_cancelar(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);

        foreach ([[$this->admin, route('admin.orders.show', $order)], [$this->waiter, route('waiter.orders')], [$this->admin, route('waiter.orders')]] as [$user, $url]) {
            $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('✏️ Editar pedido', $html, $url);
            $this->assertStringContainsString(route('admin.orders.edit', $order), $html, $url);
            $this->assertStringContainsString('❌ Cancelar pedido', $html, $url);
            $this->assertStringNotContainsString('Gestionar pedido', $html, $url);
            $this->assertStringNotContainsString('＋ Agregar', $html, $url);
        }

        $this->print($order);
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertDontSee('✏️ Editar pedido')->assertDontSee('❌ Cancelar pedido');
    }

    public function test_two_prints_of_the_same_order_in_the_same_second_never_mix(): void
    {
        $this->freezeTime();
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->hamburguesa->id => 1, $this->perro->id => 1]);
        $this->print($order);
        $this->edit($order, ['void' => [$this->line($order, $this->hamburguesa)->id => 1], 'items' => [$this->salchipapa->id => 1], 'reason' => 'Cambio'])->assertSessionHasNoErrors();
        $this->print($order);

        $first = (string) $this->line($order, $this->perro)->sent_at;
        $second = (string) $this->line($order, $this->salchipapa)->sent_at;
        $this->assertTrue($first < $second, 'La segunda impresión queda en un segundo posterior.');
        $this->assertSame($second, (string) OrderItem::where('order_id', $order->id)->whereNotNull('voided_at')->value('void_sent_at'));

        // La reimpresión solo trae la última tanda (el cambio), sin mezclar la primera.
        $kitchen = $this->tickets($this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->assertOk()->getContent())['Cocina'];
        $this->assertStringContainsString('❌ NO PREPARAR', $kitchen);
        $this->assertStringContainsString('1 × Salchipapa', $kitchen);
        $this->assertStringNotContainsString('Perro Sencillo', $kitchen);

        // Una tercera impresión en el mismo segundo también avanza.
        $this->edit($order, ['items' => [$this->perro->id => 1]])->assertSessionHasNoErrors();
        $this->print($order);
        $third = (string) OrderItem::where('order_id', $order->id)->whereNull('voided_at')->orderByDesc('id')->value('sent_at');
        $this->assertTrue($second < $third);
        $kitchen = $this->tickets($this->actingAs($this->waiter)->get(route('admin.orders.reprint', $order))->getContent())['Cocina'];
        $this->assertSame(1, substr_count($kitchen, '1 × Perro Sencillo'));
        $this->assertStringNotContainsString('Salchipapa', $kitchen);
    }

    public function test_print_button_ticket_and_history_say_cambio_or_adicion_correctly(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1], ['general_sauces' => [$this->s('Tomate')]]);
        $label = fn () => $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->getContent();
        $this->assertStringContainsString('🖨️ Imprimir comandas', $label());
        $this->print($order);

        // Adición tradicional: productos nuevos sin nada anulado.
        $this->edit($order, ['items' => [$this->salchipapa->id => 1]])->assertSessionHasNoErrors();
        $this->assertStringContainsString('🖨️ Imprimir adición', $label());
        $tickets = $this->tickets($this->print($order));
        $this->assertStringContainsString('ADICIÓN #2', $tickets['Actualización del pedido']);
        $this->assertSame('Nueva adición impresa.', $order->statusHistories()->latest('id')->value('notes'));

        // Solo se anula una salsa general enviada: es un cambio.
        $this->edit($order, ['remove_general_sauces' => [\App\Models\OrderSauce::where('order_id', $order->id)->whereNull('order_item_id')->where('sauce_id', $this->s('Tomate'))->value('id')], 'reason' => 'Sin tomate'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('🖨️ Imprimir cambio', $label());
        $this->assertStringNotContainsString('Imprimir adición', $label());
        $html = $this->print($order);
        $this->assertArrayHasKey('Pedido actualizado', $this->tickets($html));
        $this->assertStringContainsString('Salsas generales (aparte): Tomate', $this->tickets($html)['Cocina']);
        $this->assertSame('Comanda de cambio impresa.', $order->statusHistories()->latest('id')->value('notes'));

        // Solo se agrega una salsa general a un pedido ya enviado: también es un cambio.
        $this->edit($order, ['general_sauces' => [$this->s('Chimichurri')]])->assertSessionHasNoErrors();
        $this->assertStringContainsString('🖨️ Imprimir cambio', $label());
        $tickets = $this->tickets($this->print($order));
        $this->assertStringContainsString('CAMBIO · RONDA 4', $tickets['Pedido actualizado']);
        $this->assertStringNotContainsString('NO PREPARAR', $tickets['Pedido actualizado']);
        $this->assertStringContainsString('✅ PREPARAR', $tickets['Cocina']);
        $this->assertStringContainsString('Chimichurri', $tickets['Cocina']);
        $this->assertSame('Comanda de cambio impresa.', $order->statusHistories()->latest('id')->value('notes'));
    }

    public function test_edit_messages_talk_about_editing(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);
        $this->print($order);
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $order))->assertSessionHasNoErrors();

        $this->actingAs($this->waiter)->get(route('admin.orders.edit', $order))
            ->assertSessionHasErrors(['order' => 'Este pedido ya está cerrado (TERMINADO) y no se puede editar.']);
    }
}

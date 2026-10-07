<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Web\Admin\OrderController;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * "✏️ Editar pedido": agregar, quitar, cambiar cantidad, cambiar salsas y sustituir. Una edición
 * es una ronda nueva; las líneas quitadas se anulan (nunca se borran) y el pedido conserva su número.
 */
class OrderEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $waiter;

    private Product $perro;

    private Product $hamburguesa;

    private Product $salchipapa;

    private Product $granizada;

    private Product $papa;

    private RestaurantTable $table;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->waiter = User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
        $comidas = Category::create(['name' => 'Comidas', 'description' => null, 'sort_order' => 1, 'is_active' => true]);
        $granizadas = Category::create(['name' => 'Granizadas', 'description' => null, 'sort_order' => 2, 'is_active' => true]);
        $porciones = Category::create(['name' => 'Porciones', 'description' => null, 'sort_order' => 3, 'is_active' => true]);
        $this->perro = Product::create(['category_id' => $comidas->id, 'name' => 'Perro Sencillo', 'description' => null, 'price' => 13000, 'is_available' => true, 'allows_sauces' => true]);
        $this->hamburguesa = Product::create(['category_id' => $comidas->id, 'name' => 'Hamburguesa Sencilla', 'description' => null, 'price' => 23500, 'is_available' => true, 'allows_sauces' => true]);
        $this->salchipapa = Product::create(['category_id' => $comidas->id, 'name' => 'Salchipapa', 'description' => null, 'price' => 18500, 'is_available' => true, 'allows_sauces' => true]);
        $this->granizada = Product::create(['category_id' => $granizadas->id, 'name' => 'Granizada de Mora', 'description' => null, 'price' => 8500, 'is_available' => true]);
        $this->papa = Product::create(['category_id' => $porciones->id, 'name' => 'Papa francesa', 'description' => null, 'price' => 8500, 'is_available' => true, 'is_portion' => true, 'allows_sauces' => true]);
        $this->table = RestaurantTable::create(['number' => 4, 'capacity' => 4, 'qr_token' => 'mesa-4', 'status' => TableStatus::AVAILABLE]);
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

    private function edit(Order $order, array $data, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->waiter)->post(route('admin.orders.update', $order), $data);
    }

    private function line(Order $order, Product $product): OrderItem
    {
        return OrderItem::where('order_id', $order->id)->where('product_id', $product->id)->whereNull('voided_at')->orderBy('id')->firstOrFail();
    }

    /** [producto, cantidad, total, anulada] de todas las líneas, en orden. */
    private function lines(Order $order): array
    {
        return OrderItem::with('product')->where('order_id', $order->id)->orderBy('id')->get()
            ->map(fn (OrderItem $i) => [$i->product->name, (int) $i->quantity, (int) $i->total, $i->voided_at !== null])->all();
    }

    private function lastNote(Order $order): string
    {
        return (string) $order->statusHistories()->latest('id')->value('notes');
    }

    // --- Página de edición y permisos ----------------------------------------------------

    public function test_edit_page_shows_current_items_with_actions_for_admin_and_waiter(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 2, $this->granizada->id => 1]);
        $perro = $this->line($order, $this->perro);

        foreach ([$this->admin, $this->waiter] as $user) {
            $this->actingAs($user)->get(route('admin.orders.edit', $order))->assertOk()
                ->assertSee('✏️ Editar pedido #'.$order->id)->assertSee('Productos del pedido')
                ->assertSee('name="void['.$perro->id.']"', false)
                ->assertSee('🗑️ Quitar')->assertSee('🔄 Sustituir')->assertSee('🧂 Cambiar salsas')
                ->assertSee('+ Agregar producto')->assertSee('Guardar cambios')->assertSee('Motivo del cambio');
        }
        // La granizada no admite salsas: no ofrece "Cambiar salsas".
        $html = $this->actingAs($this->admin)->get(route('admin.orders.edit', $order))->getContent();
        $this->assertSame(1, substr_count($html, 'data-change-sauces='));

        // El antiguo acceso "agregar" lleva a Editar.
        $this->actingAs($this->waiter)->get(route('admin.orders.add', $order))->assertRedirect(route('admin.orders.edit', $order));
        // Sin sesión no se edita.
        auth()->logout();
        $this->post(route('admin.orders.update', $order), ['void' => [$perro->id => 1]])->assertRedirect(route('login'));
        $this->assertSame(2, (int) $perro->fresh()->quantity);
    }

    public function test_terminado_cancelado_and_dispatched_delivery_cannot_be_edited(): void
    {
        $paid = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);
        $this->print($paid);
        $this->actingAs($this->admin)->post(route('admin.orders.pay', $paid))->assertSessionHasNoErrors();

        $cancelled = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1]);
        $this->actingAs($this->waiter)->put(route('admin.orders.cancel', $cancelled), ['reason' => 'Desistió']);

        $dispatched = $this->createOrder(OrderType::DELIVERY, [$this->perro->id => 1]);
        $this->print($dispatched);
        $this->actingAs($this->waiter)->put(route('admin.orders.dispatch', $dispatched))->assertSessionHasNoErrors();

        foreach ([$paid, $cancelled, $dispatched] as $order) {
            $line = OrderItem::where('order_id', $order->id)->firstOrFail();
            $before = [$order->fresh()->status, (int) $order->fresh()->total, $order->rounds()->count()];
            $this->actingAs($this->waiter)->get(route('admin.orders.edit', $order))->assertSessionHasErrors('order');
            $this->edit($order, ['items' => [$this->salchipapa->id => 1]])->assertSessionHasErrors('order');
            $this->edit($order, ['void' => [$line->id => 1], 'reason' => 'x'])->assertSessionHasErrors('order');
            $this->assertSame($before, [$order->fresh()->status, (int) $order->fresh()->total, $order->rounds()->count()]);
            $this->assertNull($line->fresh()->voided_at);
        }
    }

    // --- Editar en PENDIENTE (nunca enviado) ----------------------------------------------

    public function test_edit_pending_order_before_printing_voids_without_kitchen_notice(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 2, $this->granizada->id => 1]);
        $perro = $this->line($order, $this->perro);

        // Sin enviar a cocina el motivo es opcional.
        $this->edit($order, ['void' => [$perro->id => 1], 'items' => [$this->salchipapa->id => 1]])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame([
            ['Perro Sencillo', 1, 13000, false],
            ['Granizada de Mora', 1, 8500, false],
            ['Perro Sencillo', 1, 13000, true],
            ['Salchipapa', 1, 18500, false],
        ], $this->lines($order));
        $voided = OrderItem::where('order_id', $order->id)->whereNotNull('voided_at')->firstOrFail();
        $this->assertSame($order->rounds()->where('number', 2)->value('id'), $voided->voided_round_id);
        $this->assertSame($this->waiter->id, $voided->voided_by_user_id);
        $this->assertNull($voided->sent_at);
        $this->assertSame(13000 + 8500 + 18500, (int) $order->subtotal);
        $this->assertSame(1500, (int) $order->packaging_fee);
        $this->assertSame(13000 + 8500 + 18500 + 1500, (int) $order->total);
        $note = $this->lastNote($order);
        $this->assertStringContainsString('✏️ Edición #1', $note);
        $this->assertStringContainsString('❌ Quitado: 1 × Perro Sencillo ($13.000)', $note);
        $this->assertStringContainsString('✅ Agregado: 1 × Salchipapa ($18.500)', $note);
        $this->assertStringContainsString('Diferencia: +$5.500', $note);

        // La primera comanda solo trae lo vigente; lo anulado nunca enviado no se anuncia.
        $html = $this->print($order);
        $this->assertStringNotContainsString('NO PREPARAR', $html);
        $this->assertStringContainsString('1 × Perro Sencillo', $html);
        $this->assertStringContainsString('1 × Salchipapa', $html);
        $this->assertNull($voided->fresh()->void_sent_at);
    }

    // --- Editar en POR COBRAR (ya enviado) -------------------------------------------------

    public function test_editing_sent_items_requires_reason_and_returns_to_pending(): void
    {
        $order = $this->createOrder(OrderType::TABLE, [$this->hamburguesa->id => 1, $this->perro->id => 2]);
        $this->print($order);
        $this->assertSame(OrderStatus::TO_COLLECT, $order->fresh()->status);
        $hamburguesa = $this->line($order, $this->hamburguesa);

        $this->edit($order, ['void' => [$hamburguesa->id => 1], 'items' => [$this->salchipapa->id => 1]])->assertSessionHasErrors('reason');
        $this->assertNull($hamburguesa->fresh()->voided_at);
        $this->assertSame(1, $order->rounds()->count(), 'Nada se guardó.');

        $this->edit($order, ['void' => [$hamburguesa->id => 1], 'items' => [$this->salchipapa->id => 1], 'reason' => 'El cliente cambió de opinión'])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $last = $order->statusHistories()->latest('id')->first();
        $this->assertSame(['POR COBRAR', 'PENDIENTE'], [$last->previous_status, $last->new_status]);
        $this->assertStringContainsString('Motivo: El cliente cambió de opinión', $last->notes);
        $this->actingAs($this->admin)->getJson(route('admin.orders.pending'))->assertJsonPath('ids', [$order->id]);
        // Agregar algo sin quitar nada no exige motivo.
        $this->print($order);
        $this->edit($order, ['items' => [$this->perro->id => 1]])->assertSessionHasNoErrors();
    }

    // --- Sustituir -------------------------------------------------------------------------

    public function test_substitution_keeps_the_order_number_and_records_prices_and_difference(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->hamburguesa->id => 1, $this->perro->id => 2]);
        $this->print($order);
        $hamburguesa = $this->line($order, $this->hamburguesa);

        $this->edit($order, ['void' => [$hamburguesa->id => 1], 'items' => [$this->salchipapa->id => 1], 'reason' => 'Cambio de producto'])->assertSessionHasNoErrors();

        $this->assertSame(1, Order::count(), 'Nunca se crea otro pedido.');
        $order->refresh();
        $this->assertSame([
            ['Hamburguesa Sencilla', 1, 23500, true],
            ['Perro Sencillo', 2, 26000, false],
            ['Salchipapa', 1, 18500, false],
        ], $this->lines($order));
        $this->assertSame(26000 + 18500, (int) $order->total);
        $note = $this->lastNote($order);
        $this->assertStringContainsString('❌ Quitado: 1 × Hamburguesa Sencilla ($23.500)', $note);
        $this->assertStringContainsString('✅ Agregado: 1 × Salchipapa ($18.500)', $note);
        $this->assertStringContainsString('Diferencia: −$5.000', $note);
        $this->assertStringContainsString('Total: $49.500 → $44.500', $note);
        // La línea retirada conserva su precio histórico, ronda y envío.
        $hamburguesa->refresh();
        $this->assertSame(23500, (int) $hamburguesa->unit_price);
        $this->assertNotNull($hamburguesa->sent_at);
        $this->assertSame($order->rounds()->where('number', 1)->value('id'), $hamburguesa->order_round_id);

        // Más caro: diferencia positiva.
        $this->print($order);
        $perro = $this->line($order, $this->perro);
        $this->edit($order, ['void' => [$perro->id => 1], 'items' => [$this->hamburguesa->id => 1], 'reason' => 'Otro cambio'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('Diferencia: +$10.500', $this->lastNote($order));
        $this->assertSame(13000 + 18500 + 23500, (int) $order->fresh()->total);
    }

    public function test_three_perros_to_two_plus_hamburguesa_splits_the_line(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 3], [
            'sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']],
        ]);
        $this->print($order);
        $perro = $this->line($order, $this->perro);

        $this->edit($order, ['void' => [$perro->id => 1], 'items' => [$this->hamburguesa->id => 1], 'reason' => 'Uno lo quiere de hamburguesa'])->assertSessionHasNoErrors();

        $this->assertSame([
            ['Perro Sencillo', 2, 26000, false],
            ['Perro Sencillo', 1, 13000, true],
            ['Hamburguesa Sencilla', 1, 23500, false],
        ], $this->lines($order));
        // La línea activa conserva su id; la anulada copia envío, ronda, notas y salsas.
        $perro->refresh();
        $voided = OrderItem::where('order_id', $order->id)->whereNotNull('voided_at')->firstOrFail();
        $this->assertSame([$perro->sent_at, $perro->order_round_id, $perro->unit_price], [$voided->sent_at, $voided->order_round_id, $voided->unit_price]);
        $this->assertSame(['Rosada'], $voided->sauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all());
        $this->assertSame(['Rosada'], $perro->sauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all());
        $this->assertSame(26000 + 23500, (int) $order->fresh()->total);
    }

    // --- Salsas ------------------------------------------------------------------------------

    public function test_change_sauces_voids_and_adds_the_same_product(): void
    {
        $order = $this->createOrder(OrderType::DELIVERY, [$this->perro->id => 1], [
            'sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']],
        ]);
        $this->print($order);
        $perro = $this->line($order, $this->perro);

        $this->edit($order, [
            'void' => [$perro->id => 1],
            'items' => [$this->perro->id => 1],
            'sauces' => [$this->perro->id => [$this->s('Tártara') => 'APARTE']],
            'reason' => 'Cambió la salsa',
        ])->assertSessionHasNoErrors();

        $active = $this->line($order, $this->perro);
        $this->assertNotSame($perro->id, $active->id);
        $this->assertSame(['Tártara (APARTE)'], $active->sauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name.' ('.$s->placement.')')->all());
        $this->assertSame(['Rosada'], $perro->fresh()->sauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all(), 'La línea anulada conserva sus salsas.');
        $this->assertSame(13000 + 3000, (int) $order->fresh()->total, 'El precio no cambia.');
    }

    public function test_substitution_respects_allows_sauces_and_never_copies_sauces(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1], [
            'sauces' => [$this->perro->id => [$this->s('Rosada') => 'EN_PRODUCTO']],
        ]);
        $perro = $this->line($order, $this->perro);

        // Granizada no admite salsas: se rechaza todo y nada cambia.
        $this->edit($order, [
            'void' => [$perro->id => 1],
            'items' => [$this->granizada->id => 1],
            'sauces' => [$this->granizada->id => [$this->s('Miel') => 'APARTE']],
        ])->assertSessionHasErrors('sauces');
        $this->assertNull($perro->fresh()->voided_at);
        $this->assertSame(1, $order->rounds()->count());

        // Sin elegir salsas, el producto nuevo no hereda las del retirado.
        $this->edit($order, ['void' => [$perro->id => 1], 'items' => [$this->salchipapa->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(0, $this->line($order, $this->salchipapa)->sauces()->count());
    }

    // --- Icopor y totales --------------------------------------------------------------------

    public function test_icopor_is_added_and_removed_per_product_and_never_negative(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->granizada->id => 2]);
        $this->assertSame(3000, (int) $order->packaging_fee);
        $granizada = $this->line($order, $this->granizada);

        // Sustituir 1 granizada (con icopor) por 1 perro (sin icopor).
        $this->edit($order, ['void' => [$granizada->id => 1], 'items' => [$this->perro->id => 1]])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame(1500, (int) $order->packaging_fee);
        $this->assertSame(8500 + 13000 + 1500, (int) $order->total);

        // Agregar granizadas suma icopor.
        $this->edit($order, ['items' => [$this->granizada->id => 2]])->assertSessionHasNoErrors();
        $this->assertSame(4500, (int) $order->fresh()->packaging_fee);

        // Nunca negativo (pedido antiguo sin icopor registrado).
        $order->update(['packaging_fee' => 0]);
        $this->edit($order, ['void' => [$this->line($order, $this->granizada)->id => 1]])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $order->fresh()->packaging_fee);

        // MESA: siempre 0.
        $mesa = $this->createOrder(OrderType::TABLE, [$this->granizada->id => 2]);
        $this->edit($mesa, ['void' => [$this->line($mesa, $this->granizada)->id => 1], 'items' => [$this->granizada->id => 3]])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) $mesa->fresh()->packaging_fee);
        $this->assertSame(4 * 8500, (int) $mesa->fresh()->total);
    }

    public function test_edit_cannot_leave_the_order_empty_or_be_empty(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 2, $this->salchipapa->id => 1]);
        $before = $this->lines($order);

        $this->edit($order, ['void' => [$this->line($order, $this->perro)->id => 2, $this->line($order, $this->salchipapa)->id => 1]])
            ->assertSessionHasErrors('void');
        $this->edit($order, [])->assertSessionHasErrors('items');
        $this->edit($order, ['void' => [$this->line($order, $this->perro)->id => 3]])->assertSessionHasErrors('void');

        $this->assertSame($before, $this->lines($order));
        $this->assertSame(1, $order->rounds()->count());
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    // --- Porciones acompañadas ---------------------------------------------------------------

    public function test_removing_a_product_with_a_paired_portion_requires_removing_or_reassigning_it(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1, $this->hamburguesa->id => 1, $this->papa->id => 1], [
            'portion_pairing' => [$this->papa->id => $this->perro->id],
        ]);
        $perro = $this->line($order, $this->perro);
        $hamburguesa = $this->line($order, $this->hamburguesa);
        $papa = $this->line($order, $this->papa);
        $this->assertSame($perro->id, $papa->paired_order_item_id);

        // Quitar el perro dejaría la porción huérfana.
        $this->edit($order, ['void' => [$perro->id => 1]])->assertSessionHasErrors('portion_target');
        $this->assertNull($perro->fresh()->voided_at);

        // Reasignarla a la hamburguesa.
        $this->edit($order, ['void' => [$perro->id => 1], 'portion_target' => [$papa->id => $hamburguesa->id]])->assertSessionHasNoErrors();
        $this->assertSame($hamburguesa->id, $papa->fresh()->paired_order_item_id);

        // O quitarla junto con su producto.
        $this->edit($order, ['void' => [$hamburguesa->id => 1, $papa->id => 1], 'items' => [$this->salchipapa->id => 1]])->assertSessionHasNoErrors();
        $this->assertNotNull($papa->fresh()->voided_at);
        // Nunca se reasigna a otra porción ni a una línea que se quita.
        $order2 = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1, $this->hamburguesa->id => 1, $this->papa->id => 1], ['portion_pairing' => [$this->papa->id => $this->perro->id]]);
        $p2 = $this->line($order2, $this->papa);
        $this->edit($order2, ['void' => [$this->line($order2, $this->perro)->id => 1, $this->line($order2, $this->hamburguesa)->id => 1], 'portion_target' => [$p2->id => $this->line($order2, $this->hamburguesa)->id]])->assertSessionHasErrors('portion_target');
    }

    public function test_new_portions_pair_with_active_lines_only(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 3]);
        $perro = $this->line($order, $this->perro);
        // Se divide la línea: la anulada (id mayor) nunca recibe la porción nueva.
        $this->edit($order, ['void' => [$perro->id => 1]])->assertSessionHasNoErrors();
        $this->edit($order, ['items' => [$this->papa->id => 1], 'portion_pairing' => [$this->papa->id => $this->perro->id]])->assertSessionHasNoErrors();
        $this->assertSame($perro->id, $this->line($order, $this->papa)->paired_order_item_id);
    }

    // --- Cuenta, reportes y concurrencia --------------------------------------------------

    public function test_voided_lines_are_out_of_account_screens_and_sales(): void
    {
        $order = $this->createOrder(OrderType::TABLE, [$this->perro->id => 2, $this->hamburguesa->id => 1]);
        $this->print($order);
        $this->edit($order, ['void' => [$this->line($order, $this->hamburguesa)->id => 1], 'reason' => 'No había pan'])->assertSessionHasNoErrors();
        $this->print($order);

        $this->actingAs($this->waiter)->get(route('admin.accounts.show', $order->table_session_id))->assertOk()
            ->assertViewHas('total', fn ($total) => (int) $total === 26000)->assertDontSee('Hamburguesa Sencilla');
        $this->actingAs($this->waiter)->get(route('waiter.orders'))->assertDontSee('Hamburguesa Sencilla');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertSee('Motivo: No había pan');

        $this->actingAs($this->admin)->post(route('admin.accounts.pay', $order->table_session_id))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->get(route('admin.reports.daily'))->assertOk()
            ->assertViewHas('summary', fn ($s) => (int) $s['revenue'] === 26000 && (int) $s['items'] === 2)
            ->assertViewHas('productSales', fn ($rows) => $rows->pluck('name')->all() === ['Perro Sencillo']);

        $this->actingAs($this->admin)->post(route('admin.reports.daily.close'))->assertOk()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_concurrent_edits_are_checked_on_the_locked_order(): void
    {
        $order = $this->createOrder(OrderType::TAKEAWAY, [$this->perro->id => 1, $this->salchipapa->id => 1]);
        $perro = $this->line($order, $this->perro);

        // Otra persona ya quitó el perro: la segunda edición con la pantalla vieja se rechaza.
        $this->edit($order, ['void' => [$perro->id => 1]])->assertSessionHasNoErrors();
        $this->edit($order, ['void' => [$perro->id => 1]])->assertSessionHasErrors('void');
        $this->assertSame(1, OrderItem::where('order_id', $order->id)->whereNotNull('voided_at')->count());

        // Pedido leído antes de cancelarse: la edición se rechaza sobre la fila bloqueada.
        $stale = Order::findOrFail($order->id);
        $this->actingAs($this->admin)->put(route('admin.orders.cancel', $order), ['reason' => 'Se fue'])->assertSessionHasNoErrors();
        $this->actingAs($this->waiter);
        try {
            app(OrderController::class)->update(Request::create('/', 'POST', ['items' => [$this->perro->id => 1]]), $stale);
            $this->fail('La edición debía rechazarse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('order', $e->errors());
        }
        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
        $this->assertSame(2, $order->rounds()->count());
        $this->assertSame(0, DB::table('order_items')->where('order_id', $order->id)->where('product_id', $this->perro->id)->whereNull('voided_at')->count());
    }
}

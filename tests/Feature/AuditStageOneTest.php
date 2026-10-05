<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\BeverageOption;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\TableSessionStatus;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de las correcciones de la etapa 1 de la auditoría.
 */
class AuditStageOneTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    }

    private function product(string $name, string $categoryName = 'Hamburguesas', int $price = 20000, bool $categoryActive = true): Product
    {
        $category = Category::firstOrCreate(
            ['name' => $categoryName],
            ['description' => null, 'sort_order' => 1, 'is_active' => $categoryActive]
        );

        return Product::create([
            'category_id' => $category->id,
            'name' => $name,
            'description' => null,
            'price' => $price,
            'is_available' => true,
        ]);
    }

    private function table(int $number, string $token): RestaurantTable
    {
        return RestaurantTable::create([
            'number' => $number,
            'capacity' => 4,
            'qr_token' => $token,
            'status' => TableStatus::AVAILABLE,
        ]);
    }

    // 1. Opciones de bebida

    public function test_beverage_options_page_renders_and_links_back_to_product_settings(): void
    {
        $admin = $this->admin();
        $soda = $this->product('Gaseosa 350 ml', 'Gaseosas y Agua', 4500);
        BeverageOption::create(['product_id' => $soda->id, 'name' => 'Coca-Cola', 'sort_order' => 1, 'is_available' => true]);

        $this->actingAs($admin)
            ->get(route('admin.beverage-options.index', $soda))
            ->assertOk()
            ->assertSee(route('admin.settings.products.edit', $soda), false);
    }

    public function test_product_without_beverage_options_redirects_to_product_settings(): void
    {
        $admin = $this->admin();
        $burger = $this->product('Hamburguesa sin opciones');

        $this->actingAs($admin)
            ->get(route('admin.beverage-options.index', $burger))
            ->assertRedirect(route('admin.settings.products.edit', $burger));
    }

    // 3. Cerrar el día

    public function test_close_day_is_blocked_while_an_order_is_not_completed(): void
    {
        $admin = $this->admin();
        $burger = $this->product('Hamburguesa activa');
        $table = $this->table(21, 'close-day-21');

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TABLE->value,
            'table_id' => $table->id,
            'items' => [$burger->id => 1],
        ]);

        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)
            ->from(route('admin.reports.daily'))
            ->post(route('admin.reports.daily.close'))
            ->assertRedirect(route('admin.reports.daily'))
            ->assertSessionHasErrors('close');

        // No se borró nada.
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => OrderStatus::PENDING->value]);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseHas('table_sessions', ['id' => $order->table_session_id, 'status' => TableSessionStatus::Active->value]);
        $this->assertSame(TableStatus::OCCUPIED, $table->fresh()->status);
    }

    public function test_close_day_still_cleans_everything_when_all_orders_are_completed(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::create(['number' => 22, 'capacity' => 4, 'qr_token' => 'close-day-22', 'status' => TableStatus::OCCUPIED]);
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::CLOSED, 'started_at' => now(), 'ended_at' => now()]);

        Order::create([
            'table_session_id' => $session->id,
            'type' => OrderType::TABLE,
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 10000,
            'tax' => 0,
            'total' => 10000,
            'paid_at' => now(),
            'paid_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.reports.daily.close'))->assertOk();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('table_sessions', 0);
        $this->assertSame(TableStatus::AVAILABLE, $table->fresh()->status);
    }

    /**
     * @return array<string, array{OrderStatus}>
     */
    public static function activeStatuses(): array
    {
        return [
            'pendiente' => [OrderStatus::PENDING],
            'en preparación' => [OrderStatus::PREPARING],
            'listo / entregado' => [OrderStatus::DELIVERED],
            'en camino' => [OrderStatus::IN_TRANSIT],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('activeStatuses')]
    public function test_close_day_is_blocked_by_every_active_status(OrderStatus $status): void
    {
        $admin = $this->admin();

        Order::create([
            'table_session_id' => null,
            'type' => OrderType::DELIVERY,
            'status' => $status,
            'subtotal' => 10000,
            'tax' => 0,
            'total' => 10000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reports.daily.close'))
            ->assertSessionHasErrors('close');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_legacy_cancelled_orders_do_not_block_close_day(): void
    {
        $admin = $this->admin();
        $table = RestaurantTable::create(['number' => 29, 'capacity' => 4, 'qr_token' => 'close-day-29', 'status' => TableStatus::OCCUPIED]);

        foreach ([OrderStatus::LEGACY_CANCELLED, OrderStatus::COMPLETED] as $status) {
            Order::create([
                'table_session_id' => null,
                'type' => OrderType::TAKEAWAY,
                'status' => $status,
                'subtotal' => 10000,
                'tax' => 0,
                'total' => 10000,
                'paid_at' => $status === OrderStatus::COMPLETED ? now() : null,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('admin.reports.daily.close'))
            ->assertOk()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(TableStatus::AVAILABLE, $table->fresh()->status);

        // La numeración de pedidos se reinicia.
        $next = Order::create([
            'table_session_id' => null,
            'type' => OrderType::TAKEAWAY,
            'status' => OrderStatus::PENDING,
            'subtotal' => 5000,
            'tax' => 0,
            'total' => 5000,
        ]);
        $this->assertSame(1, $next->id);
    }

    // 5. Seguridad del QR

    public function test_public_order_with_only_a_table_session_id_is_rejected(): void
    {
        $burger = $this->product('Hamburguesa QR');
        $table = $this->table(23, 'qr-real-23');
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);

        $this->postJson('/api/orders', [
            'table_session_id' => $session->id,
            'items' => [['product_id' => $burger->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('table_token');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_public_order_with_a_wrong_token_for_the_session_is_rejected(): void
    {
        $burger = $this->product('Hamburguesa QR 2');
        $table = $this->table(24, 'qr-real-24');
        $this->table(25, 'qr-otra-25');
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);

        $this->postJson('/api/orders', [
            'table_session_id' => $session->id,
            'table_token' => 'qr-otra-25',
            'items' => [['product_id' => $burger->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('table_token');

        $this->postJson('/api/orders', [
            'token' => 'token-inventado',
            'items' => [['product_id' => $burger->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('table_token');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_public_order_with_the_qr_token_is_still_accepted(): void
    {
        $burger = $this->product('Hamburguesa QR 3');
        $table = $this->table(26, 'qr-real-26');

        // Mismo formato que envía resources/views/client/menu.blade.php
        $this->postJson('/api/orders', [
            'token' => 'qr-real-26',
            'items' => [['product_id' => $burger->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertSame(TableStatus::OCCUPIED, $table->fresh()->status);
        $this->assertDatabaseCount('orders', 1);
    }

    // 6. Categorías deshabilitadas

    public function test_products_of_disabled_categories_are_hidden_and_rejected(): void
    {
        $admin = $this->admin();
        $active = $this->product('Hamburguesa visible');
        $hidden = $this->product('Producto oculto', 'Categoría apagada', 5000, false);
        $table = $this->table(27, 'qr-cat-27');

        $this->actingAs($admin)->get(route('admin.orders.create'))
            ->assertOk()
            ->assertSee('Hamburguesa visible')
            ->assertDontSee('Producto oculto');

        $menu = $this->getJson('/api/menu')->assertOk()->json('categories');
        $this->assertNotContains('Categoría apagada', array_column($menu, 'name'));
        $this->assertContains('Hamburguesas', array_column($menu, 'name'));

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [$hidden->id => 1],
        ])->assertSessionHasErrors('items');

        $this->postJson('/api/orders', [
            'token' => 'qr-cat-27',
            'items' => [['product_id' => $hidden->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);

        // Las adiciones tampoco aceptan productos de categorías deshabilitadas.
        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TABLE->value,
            'table_id' => $table->id,
            'items' => [$active->id => 1],
        ]);
        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.orders.add', $order))
            ->assertOk()
            ->assertDontSee('Producto oculto');

        $this->actingAs($admin)->post(route('admin.orders.add.store', $order), [
            'items' => [$hidden->id => 1],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('order_items', ['product_id' => $hidden->id]);
    }

    // 7. Historial al cobrar

    public function test_takeaway_payment_records_the_real_previous_status(): void
    {
        $admin = $this->admin();
        $order = Order::create([
            'table_session_id' => null,
            'type' => OrderType::TAKEAWAY,
            'status' => OrderStatus::DELIVERED,
            'subtotal' => 10000,
            'tax' => 0,
            'total' => 10000,
        ]);

        $this->actingAs($admin)->post(route('admin.orders.pay', $order))->assertRedirect();

        $history = $order->statusHistories()->latest('id')->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED->value, $history->previous_status);
        $this->assertSame(OrderStatus::COMPLETED->value, $history->new_status);
    }

    // 9. Reimpresión de comandas

    public function test_reprint_shows_the_last_sent_comanda_without_changing_anything(): void
    {
        $admin = $this->admin();
        $burger = $this->product('Hamburguesa inicial');
        $extra = $this->product('Hamburguesa adicional');
        $table = $this->table(28, 'qr-reprint-28');

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TABLE->value,
            'table_id' => $table->id,
            'items' => [$burger->id => 1],
        ]);
        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.orders.print', $order))->assertOk();

        // En la operación real pasan minutos entre la comanda inicial y la adición.
        $this->travel(2)->minutes();
        $this->actingAs($admin)->post(route('admin.orders.add.store', $order), ['items' => [$extra->id => 2]]);
        $this->actingAs($admin)->get(route('admin.orders.print', $order))->assertOk();

        $before = [
            'sent_at' => OrderItem::query()->orderBy('id')->pluck('sent_at')->all(),
            'items' => OrderItem::count(),
            'rounds' => $order->rounds()->count(),
            'histories' => $order->statusHistories()->count(),
            'status' => $order->fresh()->status,
        ];

        // Una nueva impresión ya no es posible (no hay productos nuevos)...
        $this->actingAs($admin)->get(route('admin.orders.print', $order))->assertSessionHasErrors('status');

        // ...pero la reimpresión sí: solo la última comanda enviada (la adición).
        $this->actingAs($admin)->get(route('admin.orders.reprint', $order))
            ->assertOk()
            ->assertSee('REIMPRESIÓN')
            ->assertSee('Hamburguesa adicional')
            ->assertDontSee('Hamburguesa inicial');

        $this->assertSame($before, [
            'sent_at' => OrderItem::query()->orderBy('id')->pluck('sent_at')->all(),
            'items' => OrderItem::count(),
            'rounds' => $order->rounds()->count(),
            'histories' => $order->statusHistories()->count(),
            'status' => $order->fresh()->status,
        ]);
    }

    public function test_reprint_requires_a_comanda_already_sent(): void
    {
        $admin = $this->admin();
        $burger = $this->product('Hamburguesa sin imprimir');

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [$burger->id => 1],
        ]);
        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.orders.reprint', $order))->assertSessionHasErrors('status');
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    // 10. HTTPS detrás del proxy

    public function test_urls_use_https_when_the_proxy_reports_https(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('http://mekatos.example.com/login');

        $response->assertOk()
            ->assertSee('https://mekatos.example.com/css/app.css', false)
            ->assertDontSee('http://mekatos.example.com/css/app.css', false);
    }

    public function test_forwarded_host_from_the_client_is_ignored(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_HOST' => 'atacante.example.net',
        ])->get('http://mekatos.example.com/login');

        $response->assertOk()
            ->assertSee('https://mekatos.example.com/css/app.css', false)
            ->assertDontSee('atacante.example.net', false);
    }
}

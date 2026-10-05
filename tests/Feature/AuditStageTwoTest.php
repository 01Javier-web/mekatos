<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\BeverageOption;
use App\Models\Category;
use App\Models\JuiceFruit;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Support\ComboOptions;
use App\TableSessionStatus;
use App\TableStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas de las correcciones de la etapa 2 de la auditoría.
 */
class AuditStageTwoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    }

    private function waiter(): User
    {
        return User::factory()->create(['role' => UserRole::Waiter, 'is_active' => true]);
    }

    private function product(string $name, string $categoryName = 'Hamburguesas', int $price = 20000): Product
    {
        $category = Category::firstOrCreate(['name' => $categoryName], ['description' => null, 'sort_order' => 1, 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => $name, 'description' => null, 'price' => $price, 'is_available' => true]);
    }

    private function table(int $number, string $token): RestaurantTable
    {
        return RestaurantTable::create(['number' => $number, 'capacity' => 4, 'qr_token' => $token, 'status' => TableStatus::AVAILABLE]);
    }

    // 1. Frutas no disponibles

    public function test_disabled_fruits_are_not_offered_in_order_forms_and_backend_still_rejects_them(): void
    {
        $admin = $this->admin();
        $this->product('Jugo Natural Jarra', 'Jugos y Bebidas Preparadas', 8500);
        $burger = $this->product('Hamburguesa');
        JuiceFruit::query()->where('name', 'Lulo')->update(['is_available' => false]);

        $this->actingAs($admin)->get(route('admin.orders.create'))
            ->assertOk()
            ->assertSee('<option value="MARACUYA">Maracuyá</option>', false)
            ->assertDontSee('<option value="LULO">', false)
            ->assertSee('<option value="OTRO">Otro</option>', false);

        $this->actingAs($admin)->post(route('admin.orders.store'), ['type' => OrderType::TAKEAWAY->value, 'items' => [$burger->id => 1]]);
        $order = Order::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.orders.add', $order))
            ->assertOk()
            ->assertDontSee('<option value="LULO">', false);

        $juice = Product::query()->where('name', 'Jugo Natural Jarra')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [$juice->id => 1],
            'juice_preparation' => [$juice->id => 'AGUA'],
            'juice_fruit' => [$juice->id => 'LULO'],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 1);
    }

    // 4. API deliver

    public function test_api_manual_deliver_step_no_longer_exists(): void
    {
        // El paso manual "listo/entregado" desapareció: la impresión lleva a ENTREGADO.
        $waiter = $this->waiter();
        $table = $this->table(31, 'qr-31');
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);
        $tableOrder = Order::create(['table_session_id' => $session->id, 'type' => OrderType::TABLE, 'status' => OrderStatus::PREPARING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);
        $takeaway = Order::create(['type' => OrderType::TAKEAWAY, 'status' => OrderStatus::PENDING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);

        $this->actingAs($waiter, 'sanctum')->putJson('/api/orders/'.$tableOrder->id.'/deliver')->assertNotFound();
        $this->actingAs($waiter, 'sanctum')->putJson('/api/orders/'.$takeaway->id.'/deliver')->assertNotFound();

        $this->assertSame(OrderStatus::PREPARING, $tableOrder->fresh()->status);
        $this->assertSame(OrderStatus::PENDING, $takeaway->fresh()->status);
    }

    // 5. Usuarios desactivados

    public function test_deactivated_user_loses_web_session(): void
    {
        $waiter = $this->waiter();
        $this->actingAs($waiter)->get(route('waiter.orders'))->assertOk();

        $waiter->update(['is_active' => false]);

        $this->get(route('waiter.orders'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');

        $this->getJson(route('admin.orders.pending'))->assertUnauthorized();
    }

    public function test_deactivated_user_json_request_is_rejected(): void
    {
        $waiter = $this->waiter();
        $waiter->update(['is_active' => false]);

        $this->actingAs($waiter)->getJson(route('admin.orders.pending'))->assertForbidden();
        $this->assertGuest('web');
    }

    public function test_deactivated_user_api_token_is_rejected(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('prueba')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/orders')->assertOk();

        $admin->update(['is_active' => false]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_active_users_keep_working_normally(): void
    {
        $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($this->waiter())->get(route('waiter.orders'))->assertOk();
    }

    // 7. Estado visible de la mesa

    public function test_legacy_preparing_orders_are_shown_as_entregado(): void
    {
        // EN PREPARACIÓN es un estado heredado: se muestra como su equivalente ENTREGADO.
        $admin = $this->admin();
        $table = $this->table(32, 'qr-32');
        $session = TableSession::create(['restaurant_table_id' => $table->id, 'status' => TableSessionStatus::Active, 'started_at' => now()]);
        $tableOrder = Order::create(['table_session_id' => $session->id, 'type' => OrderType::TABLE, 'status' => OrderStatus::PREPARING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);
        $takeaway = Order::create(['type' => OrderType::TAKEAWAY, 'status' => OrderStatus::PREPARING, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000]);

        foreach ([route('waiter.orders'), route('admin.orders.index'), route('admin.dashboard')] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertSame(2, substr_count($html, 'ENTREGADO</span>'), $url);
            $this->assertStringNotContainsString('EN PREPARACIÓN</span>', $html, $url);
            $this->assertStringNotContainsString('ABIERTA</span>', $html, $url);
        }

        // El estado almacenado no cambia (no hay UPDATE de datos heredados).
        $this->assertSame(OrderStatus::PREPARING, $tableOrder->fresh()->status);
        $this->assertSame(OrderStatus::PREPARING, $takeaway->fresh()->status);
    }

    // 8. Entrega: delivered_by / delivered_at

    public function test_delivery_salio_records_who_and_when(): void
    {
        // "🛵 Salió" (antes EN CAMINO) registra quién y cuándo. Funciona también sobre un
        // domicilio heredado EN PREPARACIÓN (equivalente a ENTREGADO).
        $waiter = $this->waiter();
        $order = Order::create(['type' => OrderType::DELIVERY, 'status' => OrderStatus::PREPARING, 'subtotal' => 1000, 'delivery_fee' => 3000, 'tax' => 0, 'total' => 4000, 'customer_name' => 'Cliente', 'customer_phone' => '300', 'delivery_address' => 'Calle 1']);

        $this->actingAs($waiter)->put(route('admin.orders.dispatch', $order))->assertRedirect(route('waiter.orders'));

        $fresh = $order->fresh();
        $this->assertSame(OrderStatus::TO_COLLECT, $fresh->status);
        $this->assertSame($waiter->id, $fresh->delivered_by_user_id);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertNull($fresh->paid_at);
    }

    // 9. Eliminaciones con claves foráneas

    public function test_deleting_records_in_use_shows_a_controlled_message(): void
    {
        $admin = $this->admin();
        $burger = $this->product('Hamburguesa vendida');
        $table = $this->table(33, 'qr-33');

        $this->actingAs($admin)->post(route('admin.orders.store'), ['type' => OrderType::TABLE->value, 'table_id' => $table->id, 'items' => [$burger->id => 1]]);

        $this->actingAs($admin)->delete(route('admin.settings.products.destroy', $burger))
            ->assertRedirect(route('admin.settings.products.index'))
            ->assertSessionHasErrors('product');
        $this->assertDatabaseHas('products', ['id' => $burger->id]);

        $this->actingAs($admin)->delete(route('admin.settings.categories.destroy', $burger->category_id))
            ->assertRedirect(route('admin.settings.categories.index'))
            ->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', ['id' => $burger->category_id]);

        $this->actingAs($admin)->delete(route('admin.tables.destroy', $table))
            ->assertRedirect(route('admin.tables.index'))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id]);

        $this->actingAs($admin, 'sanctum')->deleteJson('/api/admin/products/'.$burger->id)->assertStatus(409);
        $this->assertDatabaseHas('products', ['id' => $burger->id]);
    }

    public function test_records_without_relations_are_still_deleted(): void
    {
        $admin = $this->admin();
        $unused = $this->product('Producto sin uso');
        $table = $this->table(34, 'qr-34');

        $this->actingAs($admin)->delete(route('admin.settings.products.destroy', $unused))->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('admin.tables.destroy', $table))->assertSessionHas('success');

        $this->assertDatabaseMissing('products', ['id' => $unused->id]);
        $this->assertDatabaseMissing('restaurant_tables', ['id' => $table->id]);
    }

    // 10. Categorías vacías

    public function test_categories_page_renders_when_there_are_no_categories(): void
    {
        $admin = $this->admin();
        Product::query()->delete();
        Category::query()->delete();

        $this->actingAs($admin)->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee(route('admin.settings.categories.create'), false);
    }

    // 11. Combo / Jugo Hit

    public function test_unavailable_hit_flavors_cannot_be_selected_for_combo(): void
    {
        $admin = $this->admin();
        $burger = $this->product('Hamburguesa combo');
        $hit = $this->product('Jugos Hit', 'Jugos y Bebidas Preparadas', 4500);
        foreach (['Frutos tropicales', 'Mora'] as $i => $flavor) {
            BeverageOption::create(['product_id' => $hit->id, 'name' => $flavor, 'sort_order' => $i, 'is_available' => $flavor !== 'Mora']);
        }

        $this->assertSame(['Frutos tropicales'], ComboOptions::availableFlavors(ComboOptions::JUGO_HIT));

        $menu = $this->getJson('/api/menu')->assertOk()->json('combo.beverages.JUGO_HIT.flavors');
        $this->assertSame(['Frutos tropicales'], $menu);

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [$burger->id => 1],
            'combo' => [$burger->id => ComboOptions::YES],
            'combo_beverage_type' => [$burger->id => ComboOptions::JUGO_HIT],
            'combo_beverage_flavor' => [$burger->id => 'Mora'],
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);

        $this->actingAs($admin)->post(route('admin.orders.store'), [
            'type' => OrderType::TAKEAWAY->value,
            'items' => [$burger->id => 1],
            'combo' => [$burger->id => ComboOptions::YES],
            'combo_beverage_type' => [$burger->id => ComboOptions::JUGO_HIT],
            'combo_beverage_flavor' => [$burger->id => 'Frutos tropicales'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
    }

    // 12. Transacción al crear pedido de mesa desde el QR

    public function test_failed_qr_order_does_not_leave_the_table_occupied(): void
    {
        $table = $this->table(35, 'qr-35');
        $unavailable = $this->product('Producto agotado');
        $unavailable->update(['is_available' => false]);

        $this->postJson('/api/orders', [
            'token' => 'qr-35',
            'items' => [['product_id' => $unavailable->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('table_sessions', 0);
        $this->assertSame(TableStatus::AVAILABLE, $table->fresh()->status);
    }
}

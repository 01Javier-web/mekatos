<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_interface_renders(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Bienvenido')
            ->assertSee('Correo electrónico')
            ->assertSee('Ingresar');
    }

    public function test_client_menu_interface_renders_from_qr_route(): void
    {
        $this->get(route('client.table', ['token' => 'demo-token']))
            ->assertOk()
            ->assertSee('Menú digital')
            ->assertSee('¿Qué quieres pedir?')
            ->assertSee('Carrito');
    }

    public function test_admin_interfaces_render_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Panel administrativo');
        $this->get(route('admin.orders.index'))->assertOk()->assertSee('Pedidos registrados');
        $this->get(route('admin.orders.create'))->assertOk()->assertSee('Tipo de pedido');
        $this->get(route('admin.products.index'))->assertOk()->assertSee('Catálogo');
        $this->get(route('admin.categories.index'))->assertOk()->assertSee('Listado de categorías');
        $this->get(route('admin.tables.index'))->assertOk()->assertSee('Mesas registradas');
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Equipo de Mekatos');
    }

    public function test_waiter_interface_renders_for_waiter(): void
    {
        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);

        $this->actingAs($waiter);

        $this->get(route('waiter.orders'))
            ->assertOk()
            ->assertSee('Panel de meseros')
            ->assertSee('Pedidos activos')
            ->assertSee('Nuevo pedido');
    }

    public function test_waiter_cannot_access_admin_dashboard(): void
    {
        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);

        $this->actingAs($waiter)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}

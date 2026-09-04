<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_sees_permission_page_when_opening_admin_dashboard(): void
    {
        $waiter = User::factory()->create([
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);

        $this->actingAs($waiter)
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('No tienes permiso para entrar aquí')
            ->assertSee('403');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AdminAccountGuard;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Siempre debe quedar al menos un ADMIN funcional (activo y sin bloqueo),
 * tanto desde la gestión de usuarios web como desde la API.
 */
class AdminAccountGuardTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role, string $email, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'name' => 'Usuario '.$email,
            'email' => $email,
            'password' => 'clave-correcta-1',
            'role' => $role,
            'is_active' => true,
        ], $extra));
    }

    /**
     * ADMIN con la sesión todavía abierta pero bloqueado por intentos fallidos:
     * ya no cuenta como ADMIN funcional (el bloqueo no cierra la sesión).
     */
    private function lockedAdmin(string $email): User
    {
        $admin = $this->user(UserRole::Admin, $email);
        $admin->forceFill(['failed_login_attempts' => 3, 'locked_at' => now()])->save();

        return $admin;
    }

    /**
     * Datos del formulario de edición. is_active => null significa casilla
     * desmarcada (el navegador no envía el campo).
     */
    private function webPayload(User $user, array $overrides = []): array
    {
        return array_filter(array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active ? '1' : null,
        ], $overrides), fn ($value) => $value !== null);
    }

    // --- 1 a 3: un ADMIN no puede desactivarse, degradarse ni eliminarse a sí mismo ---

    public function test_admin_cannot_deactivate_himself_on_web(): void
    {
        $this->user(UserRole::Admin, 'otro@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->actingAs($admin)
            ->from(route('admin.users.edit', $admin))
            ->put(route('admin.users.update', $admin), $this->webPayload($admin, ['is_active' => null]))
            ->assertRedirect(route('admin.users.edit', $admin))
            ->assertSessionHasErrors(['is_active' => AdminAccountGuard::SELF_DEACTIVATE]);

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_admin_cannot_remove_his_own_admin_role_on_web(): void
    {
        $this->user(UserRole::Admin, 'otro@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->webPayload($admin, ['role' => 'MESERO']))
            ->assertSessionHasErrors(['role' => AdminAccountGuard::SELF_DEMOTE]);

        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_admin_cannot_delete_himself_on_web(): void
    {
        $this->user(UserRole::Admin, 'otro@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['user' => AdminAccountGuard::SELF_DELETE]);

        $this->assertModelExists($admin);
    }

    public function test_admin_can_still_edit_his_own_name_and_password(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), $this->webPayload($admin, ['name' => 'Nuevo nombre', 'password' => 'otra-clave-99']))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nuevo nombre', $admin->fresh()->name);
    }

    // --- 4 a 6: el último ADMIN activo no se puede desactivar, degradar ni eliminar ---

    public function test_last_functional_admin_cannot_be_deactivated_by_another_admin(): void
    {
        $actor = $this->lockedAdmin('bloqueado@mekatos.co');
        $last = $this->user(UserRole::Admin, 'ultimo@mekatos.co');

        $this->actingAs($actor)
            ->put(route('admin.users.update', $last), $this->webPayload($last, ['is_active' => null]))
            ->assertSessionHasErrors(['is_active' => AdminAccountGuard::LAST_ADMIN]);

        $this->assertTrue($last->fresh()->is_active);
    }

    public function test_last_functional_admin_cannot_be_demoted_to_waiter(): void
    {
        $actor = $this->lockedAdmin('bloqueado@mekatos.co');
        $last = $this->user(UserRole::Admin, 'ultimo@mekatos.co');

        $this->actingAs($actor)
            ->put(route('admin.users.update', $last), $this->webPayload($last, ['role' => 'MESERO']))
            ->assertSessionHasErrors(['role' => AdminAccountGuard::LAST_ADMIN]);

        $this->assertSame(UserRole::Admin, $last->fresh()->role);
    }

    public function test_last_functional_admin_cannot_be_deleted(): void
    {
        $actor = $this->lockedAdmin('bloqueado@mekatos.co');
        $last = $this->user(UserRole::Admin, 'ultimo@mekatos.co');

        $this->actingAs($actor)
            ->delete(route('admin.users.destroy', $last))
            ->assertSessionHasErrors(['user' => AdminAccountGuard::LAST_ADMIN]);

        $this->assertModelExists($last);
    }

    public function test_second_of_two_simultaneous_requests_cannot_remove_the_last_admin(): void
    {
        // A y B se desactivan mutuamente "a la vez": la petición de B ya terminó
        // (A quedó inactivo), y la de A llega con su usuario en memoria todavía activo.
        $a = $this->user(UserRole::Admin, 'a@mekatos.co');
        $b = $this->user(UserRole::Admin, 'b@mekatos.co');
        $staleA = User::find($a->id);

        AdminAccountGuard::update($b, $a, ['is_active' => false]);

        try {
            AdminAccountGuard::update($staleA, $b, ['is_active' => false]);
            $this->fail('Se permitió dejar el sistema sin ADMIN activo.');
        } catch (ValidationException $e) {
            $this->assertSame(AdminAccountGuard::LAST_ADMIN, $e->errors()['is_active'][0]);
        }

        $this->assertTrue($b->fresh()->is_active);
        $this->assertSame(1, User::where('role', 'ADMIN')->where('is_active', true)->count());
    }

    // --- 7: con dos ADMIN activos, uno puede gestionar al otro ---

    public function test_with_two_active_admins_one_can_deactivate_demote_and_delete_the_other(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $second = $this->user(UserRole::Admin, 'segundo@mekatos.co');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $second), $this->webPayload($second, ['is_active' => null]))
            ->assertSessionHasNoErrors();
        $this->assertFalse($second->fresh()->is_active);

        $third = $this->user(UserRole::Admin, 'tercero@mekatos.co');
        $this->actingAs($admin)
            ->put(route('admin.users.update', $third), $this->webPayload($third, ['role' => 'MESERO']))
            ->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Waiter, $third->fresh()->role);

        $fourth = $this->user(UserRole::Admin, 'cuarto@mekatos.co');
        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $fourth))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($fourth);

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(UserRole::Admin, $admin->fresh()->role);
    }

    public function test_inactive_admin_can_be_managed_even_if_only_one_admin_is_active(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $inactive = $this->user(UserRole::Admin, 'inactivo@mekatos.co', ['is_active' => false]);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $inactive), $this->webPayload($inactive, ['role' => 'MESERO']))
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $inactive))
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($inactive);
    }

    // --- 8: las mismas reglas en la API ---

    public function test_api_admin_cannot_deactivate_demote_or_delete_himself(): void
    {
        $this->user(UserRole::Admin, 'otro@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$admin->id}", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('errors.is_active.0', AdminAccountGuard::SELF_DEACTIVATE);

        $this->putJson("/api/admin/users/{$admin->id}", ['role' => 'MESERO'])
            ->assertStatus(422)
            ->assertJsonPath('errors.role.0', AdminAccountGuard::SELF_DEMOTE);

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.user.0', AdminAccountGuard::SELF_DELETE);

        $admin->refresh();
        $this->assertTrue($admin->is_active);
        $this->assertSame(UserRole::Admin, $admin->role);
    }

    public function test_api_last_functional_admin_cannot_be_deactivated_demoted_or_deleted(): void
    {
        $actor = $this->lockedAdmin('bloqueado@mekatos.co');
        $last = $this->user(UserRole::Admin, 'ultimo@mekatos.co');
        Sanctum::actingAs($actor);

        $this->putJson("/api/admin/users/{$last->id}", ['is_active' => false])
            ->assertStatus(422)
            ->assertJsonPath('errors.is_active.0', AdminAccountGuard::LAST_ADMIN);

        $this->putJson("/api/admin/users/{$last->id}", ['role' => 'MESERO'])
            ->assertStatus(422)
            ->assertJsonPath('errors.role.0', AdminAccountGuard::LAST_ADMIN);

        $this->deleteJson("/api/admin/users/{$last->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.user.0', AdminAccountGuard::LAST_ADMIN);

        $last->refresh();
        $this->assertTrue($last->is_active);
        $this->assertSame(UserRole::Admin, $last->role);
    }

    public function test_api_with_two_active_admins_one_can_manage_the_other(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        Sanctum::actingAs($admin);

        $second = $this->user(UserRole::Admin, 'segundo@mekatos.co');
        $this->putJson("/api/admin/users/{$second->id}", ['is_active' => false])->assertOk();
        $this->assertFalse($second->fresh()->is_active);

        $third = $this->user(UserRole::Admin, 'tercero@mekatos.co');
        $this->putJson("/api/admin/users/{$third->id}", ['role' => 'MESERO'])->assertOk();
        $this->assertSame(UserRole::Waiter, $third->fresh()->role);

        $fourth = $this->user(UserRole::Admin, 'cuarto@mekatos.co');
        $this->deleteJson("/api/admin/users/{$fourth->id}")->assertOk();
        $this->assertModelMissing($fourth);
    }

    // --- 9: los meseros se siguen administrando normalmente ---

    public function test_waiters_are_managed_normally_on_web(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->actingAs($admin)
            ->put(route('admin.users.update', $waiter), $this->webPayload($waiter, ['is_active' => null]))
            ->assertSessionHasNoErrors();
        $this->assertFalse($waiter->fresh()->is_active);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $waiter), $this->webPayload($waiter->fresh(), ['role' => 'ADMIN', 'is_active' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertSame(UserRole::Admin, $waiter->fresh()->role);

        $other = $this->user(UserRole::Waiter, 'mesero2@mekatos.co');
        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $other))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($other);
    }

    public function test_waiters_are_managed_normally_on_api(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/users/{$waiter->id}", ['is_active' => false, 'name' => 'Mesero editado'])->assertOk();
        $this->assertFalse($waiter->fresh()->is_active);
        $this->assertSame('Mesero editado', $waiter->fresh()->name);

        $this->deleteJson("/api/admin/users/{$waiter->id}")->assertOk();
        $this->assertModelMissing($waiter);
    }

    public function test_waiter_still_cannot_use_user_management(): void
    {
        $this->user(UserRole::Admin, 'admin@mekatos.co');
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $other = $this->user(UserRole::Waiter, 'mesero2@mekatos.co');

        $this->actingAs($waiter)->delete(route('admin.users.destroy', $other))->assertForbidden();
        $this->assertModelExists($other);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_an_active_admin_with_hashed_password(): void
    {
        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Dueño Mekatos')
            ->expectsQuestion('Correo electrónico', 'Dueno@Mekatos.co')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-segura-123')
            ->expectsQuestion('Repite la contraseña', 'clave-segura-123')
            ->expectsOutputToContain('Administrador dueno@mekatos.co creado correctamente.')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'dueno@mekatos.co')->firstOrFail();

        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertNotSame('clave-segura-123', $admin->password);
        $this->assertTrue(Hash::check('clave-segura-123', $admin->password));
    }

    public function test_created_admin_can_log_in(): void
    {
        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Admin')
            ->expectsQuestion('Correo electrónico', 'admin@mekatos.co')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-segura-123')
            ->expectsQuestion('Repite la contraseña', 'clave-segura-123')
            ->assertSuccessful();

        $this->post(route('login.store'), ['email' => 'admin@mekatos.co', 'password' => 'clave-segura-123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_command_rejects_mismatched_passwords(): void
    {
        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Admin')
            ->expectsQuestion('Correo electrónico', 'admin@mekatos.co')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-segura-123')
            ->expectsQuestion('Repite la contraseña', 'otra-clave-456')
            ->expectsOutputToContain('Las contraseñas no coinciden')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_command_rejects_short_password_and_invalid_email(): void
    {
        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Admin')
            ->expectsQuestion('Correo electrónico', 'admin@mekatos.co')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'corta')
            ->expectsQuestion('Repite la contraseña', 'corta')
            ->assertFailed();

        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Admin')
            ->expectsQuestion('Correo electrónico', 'no-es-un-correo')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_existing_user_is_only_changed_after_confirmation(): void
    {
        $waiter = User::factory()->create([
            'email' => 'existente@mekatos.co',
            'role' => UserRole::Waiter,
            'is_active' => false,
            'password' => 'clave-original-1',
        ]);

        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Existente')
            ->expectsQuestion('Correo electrónico', 'existente@mekatos.co')
            ->expectsConfirmation('Ya existe un usuario con el correo existente@mekatos.co. ¿Quieres asignarle una nueva contraseña y dejarlo como ADMIN activo?', 'no')
            ->assertFailed();

        $this->assertSame(UserRole::Waiter, $waiter->fresh()->role);
        $this->assertTrue(Hash::check('clave-original-1', $waiter->fresh()->password));

        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Existente')
            ->expectsQuestion('Correo electrónico', 'existente@mekatos.co')
            ->expectsConfirmation('Ya existe un usuario con el correo existente@mekatos.co. ¿Quieres asignarle una nueva contraseña y dejarlo como ADMIN activo?', 'yes')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'clave-nueva-123')
            ->expectsQuestion('Repite la contraseña', 'clave-nueva-123')
            ->assertSuccessful();

        $fresh = $waiter->fresh();
        $this->assertSame(UserRole::Admin, $fresh->role);
        $this->assertTrue($fresh->is_active);
        $this->assertTrue(Hash::check('clave-nueva-123', $fresh->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_database_seeder_creates_no_admin_and_no_fixed_password_outside_local(): void
    {
        // Se ejecuta solo sobre la base en memoria de la prueba (entorno "testing").
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['role' => UserRole::Admin->value]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_database_seeder_local_waiters_do_not_use_a_fixed_password(): void
    {
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['role' => UserRole::Admin->value]);
        $this->assertSame(2, User::query()->where('role', UserRole::Waiter->value)->count());

        foreach (User::all() as $user) {
            $this->assertFalse(Hash::check('12345678', $user->password));
        }
    }
}

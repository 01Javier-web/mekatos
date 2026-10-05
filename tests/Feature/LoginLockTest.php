<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminRecoveryNotification;
use App\Support\LoginAttempts;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Punto 6 de la etapa 2: bloqueo tras 3 intentos fallidos, desbloqueo por ADMIN
 * y recuperación de emergencia del único ADMIN funcional.
 */
class LoginLockTest extends TestCase
{
    use RefreshDatabase;

    private const RECOVERY_EMAIL = 'recuperacion@ejemplo.test';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['mekatos.admin_recovery_email' => self::RECOVERY_EMAIL]);
    }

    private function user(UserRole $role, string $email, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'email' => $email,
            'password' => 'clave-correcta-1',
            'role' => $role,
            'is_active' => true,
        ], $extra));
    }

    private function webLogin(string $email, string $password)
    {
        return $this->from(route('login'))->post(route('login.store'), ['email' => $email, 'password' => $password]);
    }

    private function lock(User $user): void
    {
        foreach (range(1, 3) as $i) {
            $this->webLogin($user->email, 'clave-incorrecta');
        }
    }

    private function recoveryUrl(): string
    {
        $url = null;
        Notification::assertSentTo(new AnonymousNotifiable, AdminRecoveryNotification::class, function ($notification, $channels, $notifiable) use (&$url) {
            $url = $notification->url;

            return $notifiable->routes['mail'] === self::RECOVERY_EMAIL;
        });

        return $url;
    }

    private function tokenFrom(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH));
    }

    // --- Contador y bloqueo ---

    public function test_failures_count_one_two_and_lock_on_third(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->webLogin($waiter->email, 'mala-1')->assertSessionHasErrors('email');
        $this->assertSame(1, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);

        $this->webLogin($waiter->email, 'mala-2');
        $this->assertSame(2, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);

        $this->webLogin($waiter->email, 'mala-3');
        $this->assertSame(3, $waiter->fresh()->failed_login_attempts);
        $this->assertNotNull($waiter->fresh()->locked_at);
        $this->assertTrue($waiter->fresh()->is_active, 'El bloqueo no toca is_active.');
        $this->assertGuest();
    }

    // --- Ventana de 15 minutos ---

    public function test_window_starts_on_first_failure_and_is_kept_by_the_next_ones(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $this->freezeSecond();

        $this->webLogin($waiter->email, 'mala-1');
        $start = $waiter->fresh()->failed_login_window_started_at;
        $this->assertNotNull($start);
        $this->assertSame(1, $waiter->fresh()->failed_login_attempts);

        $this->travel(5)->minutes();
        $this->webLogin($waiter->email, 'mala-2');
        $this->assertSame(2, $waiter->fresh()->failed_login_attempts);
        $this->assertTrue($start->equalTo($waiter->fresh()->failed_login_window_started_at), 'La ventana no se desplaza con cada fallo.');

        $this->travel(5)->minutes();
        $this->webLogin($waiter->email, 'mala-3');
        $this->assertSame(3, $waiter->fresh()->failed_login_attempts);
        $this->assertNotNull($waiter->fresh()->locked_at);
    }

    public function test_third_failure_exactly_at_15_minutes_still_locks(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $this->freezeSecond();

        $this->webLogin($waiter->email, 'mala-1');
        $this->webLogin($waiter->email, 'mala-2');
        $this->travel(LoginAttempts::WINDOW_MINUTES)->minutes();
        $this->webLogin($waiter->email, 'mala-3');

        $this->assertNotNull($waiter->fresh()->locked_at);
    }

    public function test_three_failures_spread_over_more_than_15_minutes_do_not_lock(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->webLogin($waiter->email, 'mala-1');
        $this->travel(16)->minutes();
        $this->webLogin($waiter->email, 'mala-2');
        $this->travel(16)->minutes();
        $this->webLogin($waiter->email, 'mala-3');

        $this->assertSame(1, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);
        $this->webLogin($waiter->email, 'clave-correcta-1')->assertRedirect(route('waiter.orders'));
        $this->assertAuthenticatedAs($waiter);
    }

    public function test_window_counts_from_its_first_failure_not_from_the_last_one(): void
    {
        // Fallos a los 0, 10 y 20 minutos: el tercero ya está fuera de la ventana
        // abierta a los 0 minutos, así que empieza una serie nueva y no bloquea.
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->webLogin($waiter->email, 'mala-1');
        $this->travel(10)->minutes();
        $this->webLogin($waiter->email, 'mala-2');
        $this->assertSame(2, $waiter->fresh()->failed_login_attempts);

        $this->travel(10)->minutes();
        $this->webLogin($waiter->email, 'mala-3');

        $this->assertSame(1, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);
    }

    public function test_after_window_expires_a_new_failure_starts_again_from_one(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->webLogin($waiter->email, 'mala-1');
        $this->webLogin($waiter->email, 'mala-2');
        $oldStart = $waiter->fresh()->failed_login_window_started_at;

        $this->travel(16)->minutes();
        $this->freezeSecond();
        $this->webLogin($waiter->email, 'mala-3');

        $fresh = $waiter->fresh();
        $this->assertSame(1, $fresh->failed_login_attempts);
        $this->assertNull($fresh->locked_at);
        $this->assertTrue($fresh->failed_login_window_started_at->greaterThan($oldStart));
        $this->assertTrue($fresh->failed_login_window_started_at->equalTo(now()));

        // La nueva ventana vuelve a bloquear al tercer fallo.
        $this->webLogin($waiter->email, 'mala-4');
        $this->webLogin($waiter->email, 'mala-5');
        $this->assertNotNull($waiter->fresh()->locked_at);
    }

    public function test_successful_login_resets_counter_and_window(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->webLogin($waiter->email, 'mala-1');
        $this->webLogin($waiter->email, 'mala-2');
        $this->webLogin($waiter->email, 'clave-correcta-1')->assertRedirect(route('waiter.orders'));

        $fresh = $waiter->fresh();
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertNull($fresh->failed_login_window_started_at);

        // Tras el login correcto hacen falta otros 3 fallos para bloquear.
        $this->post(route('logout'));
        $this->webLogin($waiter->email, 'mala-3');
        $this->webLogin($waiter->email, 'mala-4');
        $this->assertSame(2, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);
    }

    public function test_locked_account_stays_locked_after_the_window_expires(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $this->lock($waiter);

        $this->travel(2)->hours();

        $this->webLogin($waiter->email, 'clave-correcta-1')
            ->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);
        $this->webLogin($waiter->email, 'mala')->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);
        $this->assertGuest();
        $this->assertNotNull($waiter->fresh()->locked_at);
        $this->assertSame(3, $waiter->fresh()->failed_login_attempts);
    }

    public function test_unlock_clears_counter_and_window(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $this->lock($waiter);

        $this->actingAs($admin)->post(route('admin.users.unlock', $waiter))->assertRedirect(route('admin.users.index'));
        $this->post(route('logout'));

        $fresh = $waiter->fresh();
        $this->assertNull($fresh->locked_at);
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertNull($fresh->failed_login_window_started_at);

        $this->webLogin($waiter->email, 'mala');
        $this->assertSame(1, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);
    }

    public function test_admin_and_waiter_follow_the_same_counter_and_window(): void
    {
        $this->user(UserRole::Admin, 'otro-admin@mekatos.co');

        foreach ([UserRole::Admin, UserRole::Waiter] as $role) {
            $user = $this->user($role, strtolower($role->value).'@mekatos.co');

            $this->webLogin($user->email, 'mala-1');
            $this->webLogin($user->email, 'mala-2');
            $this->assertSame(2, $user->fresh()->failed_login_attempts, $role->value);

            $this->travel(16)->minutes();
            $this->webLogin($user->email, 'mala-3');
            $this->assertSame(1, $user->fresh()->failed_login_attempts, $role->value);
            $this->assertNull($user->fresh()->locked_at, $role->value);

            $this->webLogin($user->email, 'mala-4');
            $this->webLogin($user->email, 'mala-5');
            $this->assertNotNull($user->fresh()->locked_at, $role->value);

            $this->webLogin($user->email, 'clave-correcta-1')->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);
            $this->assertGuest();
        }
    }

    public function test_api_failures_use_the_same_window(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->postJson('/api/login', ['email' => $waiter->email, 'password' => 'mala'])->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $waiter->email, 'password' => 'mala'])->assertUnauthorized();
        $this->travel(16)->minutes();
        $this->postJson('/api/login', ['email' => $waiter->email, 'password' => 'mala'])->assertUnauthorized();

        $this->assertSame(1, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);
    }

    // --- Límite de peticiones del login web (independiente del bloqueo por cuenta) ---

    public function test_web_login_is_rate_limited_per_email(): void
    {
        foreach (range(1, 5) as $i) {
            $this->webLogin('no-existe@mekatos.co', 'mala')->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);
        }

        $this->webLogin('no-existe@mekatos.co', 'mala')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => LoginAttempts::THROTTLED_ERROR]);

        // Otro correo desde la misma IP todavía puede intentarlo.
        $this->webLogin('otro@mekatos.co', 'mala')->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);

        // Pasado el minuto se puede volver a intentar.
        $this->travel(61)->seconds();
        $this->webLogin('no-existe@mekatos.co', 'mala')->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);
    }

    public function test_web_login_is_rate_limited_per_ip_and_throttled_requests_do_not_touch_accounts(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        foreach (range(1, 20) as $i) {
            $this->webLogin("intruso{$i}@mekatos.co", 'mala');
        }

        // La IP superó el límite: la petición no llega al controlador, así que
        // ni inicia sesión ni suma fallos a la cuenta.
        $this->webLogin($waiter->email, 'mala')->assertSessionHasErrors(['email' => LoginAttempts::THROTTLED_ERROR]);
        $this->webLogin($waiter->email, 'clave-correcta-1')->assertSessionHasErrors(['email' => LoginAttempts::THROTTLED_ERROR]);
        $this->assertGuest();
        $this->assertSame(0, $waiter->fresh()->failed_login_attempts);

        // Desde otra IP la cuenta funciona con normalidad.
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40']);
        $this->webLogin($waiter->email, 'clave-correcta-1')->assertRedirect(route('waiter.orders'));
        $this->assertAuthenticatedAs($waiter);
    }

    public function test_rate_limit_does_not_affect_normal_logins_from_the_same_place(): void
    {
        // Inicio de turno: varios meseros entran desde la misma IP del local,
        // alguno se equivoca una vez, y todos consiguen entrar.
        foreach (range(1, 8) as $i) {
            $waiter = $this->user(UserRole::Waiter, "mesero{$i}@mekatos.co");

            if ($i % 2 === 0) {
                $this->webLogin($waiter->email, 'me-equivoque')->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);
            }

            $this->webLogin($waiter->email, 'clave-correcta-1')->assertRedirect(route('waiter.orders'));
            $this->assertAuthenticatedAs($waiter);
            $this->post(route('logout'));
        }
    }

    public function test_api_keeps_its_own_rate_limit_and_the_account_lock(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        foreach (range(1, 10) as $i) {
            $this->postJson('/api/login', ['email' => "otro{$i}@mekatos.co", 'password' => 'mala'])->assertUnauthorized();
        }

        $this->postJson('/api/login', ['email' => $waiter->email, 'password' => 'clave-correcta-1'])->assertStatus(429);

        // El límite de la web es independiente: la web sigue respondiendo.
        $this->webLogin($waiter->email, 'clave-correcta-1')->assertRedirect(route('waiter.orders'));
    }

    public function test_correct_password_after_lock_is_rejected(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $this->lock($waiter);

        $this->webLogin($waiter->email, 'clave-correcta-1')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => LoginAttempts::GENERIC_ERROR]);
        $this->assertGuest();
        $this->assertNotNull($waiter->fresh()->locked_at);
    }

    public function test_successful_login_before_third_failure_resets_counter(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->webLogin($waiter->email, 'mala-1');
        $this->webLogin($waiter->email, 'mala-2');
        $this->assertSame(2, $waiter->fresh()->failed_login_attempts);

        $this->webLogin($waiter->email, 'clave-correcta-1')->assertRedirect(route('waiter.orders'));
        $this->assertAuthenticatedAs($waiter);
        $this->assertSame(0, $waiter->fresh()->failed_login_attempts);
        $this->assertNull($waiter->fresh()->locked_at);
    }

    public function test_api_login_respects_the_same_lock(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/login', ['email' => $waiter->email, 'password' => 'mala'])->assertUnauthorized();
        }

        $this->assertNotNull($waiter->fresh()->locked_at);

        // Ni por la API ni por la web se obtiene acceso con la contraseña correcta.
        $this->postJson('/api/login', ['email' => $waiter->email, 'password' => 'clave-correcta-1'])->assertUnauthorized();
        $this->webLogin($waiter->email, 'clave-correcta-1')->assertSessionHasErrors('email');
        $this->assertSame(0, $waiter->tokens()->count());
    }

    public function test_inactive_user_is_still_rejected(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'inactivo@mekatos.co', ['is_active' => false]);

        $this->webLogin($waiter->email, 'clave-correcta-1')->assertSessionHasErrors('email');
        $this->postJson('/api/login', ['email' => $waiter->email, 'password' => 'clave-correcta-1'])->assertUnauthorized();
        $this->assertGuest();
        $this->assertNull($waiter->fresh()->locked_at);
    }

    public function test_locked_admin_also_respects_the_lock(): void
    {
        $this->user(UserRole::Admin, 'otro-admin@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $this->lock($admin);

        $this->webLogin($admin->email, 'clave-correcta-1')->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertNotNull($admin->fresh()->locked_at);
    }

    public function test_unknown_email_does_not_reveal_existence_or_create_anything(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $wrongPassword = $this->webLogin($waiter->email, 'mala')->assertSessionHasErrors('email');
        $wrongMessage = session('errors')->first('email');

        $unknown = $this->webLogin('no-existe@mekatos.co', 'mala')->assertSessionHasErrors('email');
        $this->assertSame($wrongMessage, session('errors')->first('email'));

        foreach (range(1, 4) as $i) {
            $this->webLogin('no-existe@mekatos.co', 'mala');
        }

        $this->postJson('/api/login', ['email' => 'no-existe@mekatos.co', 'password' => 'mala'])
            ->assertUnauthorized()
            ->assertJson(['message' => 'Datos incorrectos, intenta de nuevo']);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'no-existe@mekatos.co']);
        Notification::assertNothingSent();
    }

    // --- Desbloqueo por ADMIN ---

    public function test_admin_can_unlock_without_changing_is_active(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $this->lock($waiter);

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Bloqueado')
            ->assertSee(route('admin.users.unlock', $waiter), false);

        $this->actingAs($admin)->post(route('admin.users.unlock', $waiter))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $fresh = $waiter->fresh();
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertNull($fresh->locked_at);
        $this->assertTrue($fresh->is_active);

        // Un usuario inactivo desbloqueado sigue inactivo.
        $inactive = $this->user(UserRole::Waiter, 'inactivo@mekatos.co', ['is_active' => false]);
        $inactive->forceFill(['failed_login_attempts' => 3, 'locked_at' => now()])->save();
        $this->actingAs($admin)->post(route('admin.users.unlock', $inactive));
        $this->assertNull($inactive->fresh()->locked_at);
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_waiter_cannot_unlock(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $locked = $this->user(UserRole::Waiter, 'bloqueado@mekatos.co');
        $locked->forceFill(['failed_login_attempts' => 3, 'locked_at' => now()])->save();

        $this->actingAs($waiter)->post(route('admin.users.unlock', $locked))->assertForbidden();
        $this->actingAs($waiter)->post(route('admin.users.unlock', $waiter))->assertForbidden();

        $this->assertNotNull($locked->fresh()->locked_at);
        $this->assertSame(3, $locked->fresh()->failed_login_attempts);
    }

    public function test_lock_fields_cannot_be_changed_through_user_update(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $waiter->forceFill(['failed_login_attempts' => 3, 'locked_at' => now()])->save();

        $this->actingAs($admin)->put(route('admin.users.update', $waiter), [
            'name' => 'Mesero', 'email' => $waiter->email, 'role' => 'MESERO', 'is_active' => '1',
            'failed_login_attempts' => 0, 'locked_at' => null,
        ]);

        $this->assertNotNull($waiter->fresh()->locked_at);
    }

    // --- Recuperación de emergencia ---

    public function test_no_emergency_recovery_when_another_functional_admin_exists(): void
    {
        $this->user(UserRole::Admin, 'otro-admin@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->lock($admin);

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_inactive_or_locked_admins_do_not_count_as_functional(): void
    {
        $this->user(UserRole::Admin, 'inactivo@mekatos.co', ['is_active' => false]);
        $this->user(UserRole::Admin, 'bloqueado@mekatos.co', ['failed_login_attempts' => 3, 'locked_at' => now()]);
        $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->lock($admin);

        Notification::assertSentTimes(AdminRecoveryNotification::class, 1);
    }

    public function test_only_admin_locked_triggers_recovery_with_hashed_single_token(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->lock($admin);

        $url = $this->recoveryUrl();
        $token = $this->tokenFrom($url);

        $row = DB::table('password_reset_tokens')->where('email', $admin->email)->first();
        $this->assertNotNull($row);
        $this->assertNotSame($token, $row->token, 'El token no se guarda en texto plano.');
        $this->assertTrue(Hash::check($token, $row->token));
        $this->assertGreaterThanOrEqual(64, strlen($token));

        // Más intentos mientras el enlace sigue vigente no generan correos nuevos.
        $this->webLogin($admin->email, 'clave-correcta-1');
        $this->webLogin($admin->email, 'otra-mala');
        Notification::assertSentTimes(AdminRecoveryNotification::class, 1);
    }

    public function test_waiter_lock_never_sends_admin_recovery(): void
    {
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');

        $this->lock($waiter);

        Notification::assertNothingSent();
    }

    public function test_valid_token_unlocks_and_requires_a_new_password(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $this->lock($admin);
        $url = $this->recoveryUrl();
        $token = $this->tokenFrom($url);

        $this->get($url)->assertOk()->assertSee('Contraseña nueva')->assertDontSee('no es válido');

        // Sin confirmación correcta no se cambia nada.
        $this->post(route('admin.recovery.update'), [
            'token' => $token, 'email' => $admin->email, 'password' => 'nueva-clave-99', 'password_confirmation' => 'otra',
        ])->assertSessionHasErrors('password');
        $this->assertNotNull($admin->fresh()->locked_at);

        $this->post(route('admin.recovery.update'), [
            'token' => $token, 'email' => $admin->email, 'password' => 'nueva-clave-99', 'password_confirmation' => 'nueva-clave-99',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $fresh = $admin->fresh();
        $this->assertNull($fresh->locked_at);
        $this->assertSame(0, $fresh->failed_login_attempts);
        $this->assertTrue($fresh->is_active);
        $this->assertTrue(Hash::check('nueva-clave-99', $fresh->password));
        $this->assertFalse(Hash::check('clave-correcta-1', $fresh->password), 'La contraseña anterior deja de servir.');
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->webLogin($admin->email, 'nueva-clave-99')->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($fresh);
    }

    public function test_used_token_cannot_be_reused(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $this->lock($admin);
        $url = $this->recoveryUrl();
        $token = $this->tokenFrom($url);

        $data = ['token' => $token, 'email' => $admin->email, 'password' => 'nueva-clave-99', 'password_confirmation' => 'nueva-clave-99'];
        $this->post(route('admin.recovery.update'), $data)->assertRedirect(route('login'));

        $this->get($url)->assertOk()->assertSee('no es válido');
        $this->post(route('admin.recovery.update'), array_merge($data, ['password' => 'tercera-clave-1', 'password_confirmation' => 'tercera-clave-1']))
            ->assertSessionHasErrors('token');
        $this->assertTrue(Hash::check('nueva-clave-99', $admin->fresh()->password));
    }

    public function test_expired_token_does_not_work_and_a_new_attempt_issues_a_new_link(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $this->lock($admin);
        $url = $this->recoveryUrl();
        $token = $this->tokenFrom($url);

        $this->travel(31)->minutes();

        $this->get($url)->assertOk()->assertSee('no es válido');
        $this->post(route('admin.recovery.update'), [
            'token' => $token, 'email' => $admin->email, 'password' => 'nueva-clave-99', 'password_confirmation' => 'nueva-clave-99',
        ])->assertSessionHasErrors('token');
        $this->assertNotNull($admin->fresh()->locked_at);

        // Al vencer, un nuevo intento de acceso emite un enlace nuevo (sigue bloqueado).
        $this->webLogin($admin->email, 'clave-correcta-1')->assertSessionHasErrors('email');
        Notification::assertSentTimes(AdminRecoveryNotification::class, 2);
        $this->assertNotNull($admin->fresh()->locked_at);
    }

    public function test_token_cannot_be_used_for_another_account(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $waiter = $this->user(UserRole::Waiter, 'mesero@mekatos.co');
        $this->lock($admin);
        $token = $this->tokenFrom($this->recoveryUrl());

        $this->post(route('admin.recovery.update'), [
            'token' => $token, 'email' => $waiter->email, 'password' => 'nueva-clave-99', 'password_confirmation' => 'nueva-clave-99',
        ])->assertSessionHasErrors('token');

        $this->assertTrue(Hash::check('clave-correcta-1', $waiter->fresh()->password));
    }

    public function test_admin_unlock_invalidates_pending_recovery_link(): void
    {
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $this->lock($admin);
        $url = $this->recoveryUrl();

        // Otro ADMIN creado después (por ejemplo con mekatos:crear-admin) lo desbloquea.
        $other = $this->user(UserRole::Admin, 'nuevo-admin@mekatos.co');
        $this->actingAs($other)->post(route('admin.users.unlock', $admin));

        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->get($url)->assertSee('no es válido');
    }

    // --- El enlace de recuperación no depende del host de la petición ---

    public function test_malicious_forwarded_host_does_not_change_recovery_link_domain(): void
    {
        config(['app.url' => 'https://mekatos.example.com']);
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.7',
            'HTTP_X_FORWARDED_HOST' => 'atacante.example.net',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);
        $this->lock($admin);

        $url = $this->recoveryUrl();

        $this->assertStringStartsWith('https://mekatos.example.com/recuperar-admin/', $url);
        $this->assertStringNotContainsString('atacante', $url);
    }

    public function test_spoofed_host_header_does_not_change_recovery_link_domain(): void
    {
        config(['app.url' => 'https://mekatos.example.com']);
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        foreach (range(1, 3) as $i) {
            $this->from('http://atacante.example.net/login')
                ->post('http://atacante.example.net/login', ['email' => $admin->email, 'password' => 'clave-incorrecta']);
        }

        $url = $this->recoveryUrl();

        $this->assertSame('mekatos.example.com', parse_url($url, PHP_URL_HOST));
        $this->assertStringNotContainsString('atacante', $url);
    }

    public function test_recovery_link_from_app_url_still_unlocks_the_admin(): void
    {
        config(['app.url' => 'https://mekatos.example.com/']);
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $this->lock($admin);

        $url = $this->recoveryUrl();
        $this->assertStringStartsWith('https://mekatos.example.com/recuperar-admin/', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('admin@mekatos.co', $query['email'] ?? null);

        $this->get($url)->assertOk()->assertSee('Contraseña nueva');
        $this->post(route('admin.recovery.update'), [
            'token' => $this->tokenFrom($url),
            'email' => $admin->email,
            'password' => 'otra-clave-segura-1',
            'password_confirmation' => 'otra-clave-segura-1',
        ])->assertRedirect(route('login'));

        $this->assertNull($admin->fresh()->locked_at);
    }

    public function test_missing_app_url_sends_nothing_and_stores_no_token(): void
    {
        config(['app.url' => '']);
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->lock($admin);

        $this->assertNotNull($admin->fresh()->locked_at);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_missing_recovery_email_sends_nothing_and_stores_no_token(): void
    {
        config(['mekatos.admin_recovery_email' => null]);
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');

        $this->lock($admin);

        $this->assertNotNull($admin->fresh()->locked_at);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_create_admin_command_does_not_unlock_silently(): void
    {
        $this->user(UserRole::Admin, 'otro-admin@mekatos.co');
        $admin = $this->user(UserRole::Admin, 'admin@mekatos.co');
        $this->lock($admin);

        $this->artisan('mekatos:crear-admin')
            ->expectsQuestion('Nombre', 'Admin')
            ->expectsQuestion('Correo electrónico', 'admin@mekatos.co')
            ->expectsConfirmation('Ya existe un usuario con el correo admin@mekatos.co. ¿Quieres asignarle una nueva contraseña y dejarlo como ADMIN activo?', 'yes')
            ->expectsOutputToContain('NO lo desbloquea')
            ->expectsQuestion('Contraseña (mínimo 8 caracteres)', 'nueva-clave-99')
            ->expectsQuestion('Repite la contraseña', 'nueva-clave-99')
            ->assertSuccessful();

        $this->assertNotNull($admin->fresh()->locked_at);
        $this->webLogin($admin->email, 'nueva-clave-99')->assertSessionHasErrors('email');
    }
}

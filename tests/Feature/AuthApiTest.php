<?php

namespace Tests\Feature;

use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_login_and_receive_a_sanctum_token(): void
    {
        $user = User::factory()->create([
            'email' => 'mesero@test.com',
            'password' => Hash::make('password123'),
            'role' => UserRole::Waiter,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'mesero@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('role', UserRole::Waiter->value)
            ->assertJsonStructure(['user', 'token', 'message', 'role']);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('correct-password'),
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'admin@test.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }
}

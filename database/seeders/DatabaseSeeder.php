<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@mekatos.test'],
            User::factory()->make([
                'name' => 'Administrador Mekatos',
            ])->getAttributes()
        );

        $user->forceFill([
            'role' => UserRole::Admin,
            'is_active' => true,
        ])->save();
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedUser([
            'email' => 'admin@mekatos.test',
            'name' => 'Administrador Principal',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $this->seedUser([
            'email' => 'gerencia@mekatos.test',
            'name' => 'Gerencia Mekatos',
            'password' => 'password',
            'role' => UserRole::Admin,
        ]);

        $this->seedUser([
            'email' => 'mesero1@mekatos.test',
            'name' => 'Carlos Martínez',
            'password' => 'password',
            'role' => UserRole::Waiter,
        ]);

        $this->seedUser([
            'email' => 'mesero2@mekatos.test',
            'name' => 'Laura Gómez',
            'password' => 'password',
            'role' => UserRole::Waiter,
        ]);

        $this->seedUser([
            'email' => 'mesero3@mekatos.test',
            'name' => 'Andrés Rodríguez',
            'password' => 'password',
            'role' => UserRole::Waiter,
        ]);

        $this->call(MekatosMenuSeeder::class);
    }

    private function seedUser(array $data): void
    {
        User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'is_active' => true,
            ]
        );
    }
}

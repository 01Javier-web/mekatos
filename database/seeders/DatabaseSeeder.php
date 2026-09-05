<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $oldUsers = [
            'admin@mekatos.test',
            'gerencia@mekatos.test',
            'mesero1@mekatos.test',
            'mesero2@mekatos.test',
            'mesero3@mekatos.test',
        ];

        $oldUserIds = User::whereIn('email', $oldUsers)->pluck('id');

        // Preserve existing orders/history while removing the old test accounts.
        DB::table('orders')
            ->whereIn('handled_by_user_id', $oldUserIds)
            ->update(['handled_by_user_id' => null]);

        DB::table('orders')
            ->whereIn('delivered_by_user_id', $oldUserIds)
            ->update(['delivered_by_user_id' => null]);

        DB::table('order_status_histories')
            ->whereIn('changed_by_user_id', $oldUserIds)
            ->update(['changed_by_user_id' => null]);

        User::whereIn('id', $oldUserIds)->delete();

        $this->seedUser([
            'email' => 'admin1@mekatos.test',
            'name' => 'Administrador 1',
            'password' => '12345678',
            'role' => UserRole::Admin,
        ]);

        $this->seedUser([
            'email' => 'admin2@mekatos.test',
            'name' => 'Administrador 2',
            'password' => '12345678',
            'role' => UserRole::Admin,
        ]);

        $this->seedUser([
            'email' => 'mesero1@mekatos.test',
            'name' => 'Mesero 1',
            'password' => '12345678',
            'role' => UserRole::Waiter,
        ]);

        $this->seedUser([
            'email' => 'mesero2@mekatos.test',
            'name' => 'Mesero 2',
            'password' => '12345678',
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

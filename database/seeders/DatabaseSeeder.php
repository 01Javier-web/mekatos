<?php

namespace Database\Seeders;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $oldUsers = ['admin@mekatos.test','gerencia@mekatos.test','mesero1@mekatos.test','mesero2@mekatos.test','mesero3@mekatos.test'];
        $oldUserIds = User::whereIn('email', $oldUsers)->pluck('id');

        DB::table('orders')->whereIn('handled_by_user_id', $oldUserIds)->update(['handled_by_user_id' => null]);
        DB::table('orders')->whereIn('delivered_by_user_id', $oldUserIds)->update(['delivered_by_user_id' => null]);
        DB::table('orders')->whereIn('paid_by_user_id', $oldUserIds)->update(['paid_by_user_id' => null]);
        DB::table('order_status_histories')->whereIn('changed_by_user_id', $oldUserIds)->update(['changed_by_user_id' => null]);
        User::whereIn('id', $oldUserIds)->delete();

        // Las cuentas ADMIN no se crean desde el seeder ni con contraseñas fijas.
        // Para crear un administrador: php artisan mekatos:crear-admin

        // Meseros de prueba: solo en desarrollo local y con contraseña aleatoria
        // que se muestra en la consola al ejecutar el seeder.
        if (app()->environment('local')) {
            $this->seedUser(['email'=>'mesero1@mekatos.test','name'=>'Mesero 1','role'=>UserRole::Waiter]);
            $this->seedUser(['email'=>'mesero2@mekatos.test','name'=>'Mesero 2','role'=>UserRole::Waiter]);
        }

        $this->call(MekatosMenuSeeder::class);
        $this->call(RestaurantTableSeeder::class);
    }

    private function seedUser(array $data): void
    {
        $password = Str::password(16, symbols: false);

        User::updateOrCreate(['email'=>$data['email']], ['name'=>$data['name'],'password'=>Hash::make($password),'role'=>$data['role'],'is_active'=>true]);

        $this->command?->info("Usuario de prueba (solo local): {$data['email']} / {$password}");
    }
}

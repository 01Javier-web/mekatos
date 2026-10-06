<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderSauce;
use App\Models\Sauce;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemovePinaSobreSauceTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_05_000005_remove_pina_sobre_sauce.php');
    }

    public function test_pina_sobre_is_removed_and_pina_is_kept(): void
    {
        $this->assertFalse(Sauce::where('name', 'Piña sobre')->exists());
        $this->assertTrue(Sauce::where('name', 'Piña')->where('is_active', true)->exists());
        $this->assertSame(16, Sauce::count());
    }

    public function test_without_history_the_migration_deletes_pina_sobre(): void
    {
        $this->migration()->down();
        $before = Sauce::where('name', '!=', 'Piña sobre')->orderBy('id')->get(['id', 'name', 'sort_order', 'is_active'])->toArray();
        $this->assertSame(17, Sauce::count());

        $this->migration()->up();

        $this->assertFalse(Sauce::where('name', 'Piña sobre')->exists());
        $this->assertSame($before, Sauce::orderBy('id')->get(['id', 'name', 'sort_order', 'is_active'])->toArray(), 'Las demás salsas no cambian.');
    }

    public function test_with_history_the_migration_deactivates_pina_sobre_and_keeps_the_order(): void
    {
        $this->migration()->down();
        $pinaSobre = Sauce::where('name', 'Piña sobre')->firstOrFail();

        $order = Order::create(['type' => 'PARA_LLEVAR', 'status' => 'TERMINADO', 'subtotal' => 0, 'packaging_fee' => 0, 'delivery_fee' => 0, 'tax' => 0, 'total' => 0]);
        DB::table('order_sauces')->insert(['order_id' => $order->id, 'order_item_id' => null, 'order_round_id' => null, 'sauce_id' => $pinaSobre->id, 'placement' => 'APARTE', 'created_at' => now(), 'updated_at' => now()]);

        $this->migration()->up();

        $this->assertFalse($pinaSobre->fresh()->is_active);
        $this->assertSame(1, OrderSauce::where('order_id', $order->id)->where('sauce_id', $pinaSobre->id)->count());
        $this->assertSame(['Piña sobre'], $order->generalSauces()->with('sauce')->get()->map(fn ($s) => $s->sauce->name)->all());
        $this->assertFalse(Sauce::query()->selectable()->where('name', 'Piña sobre')->exists(), 'Ya no se ofrece en pedidos nuevos.');
        $this->assertTrue(Sauce::where('name', 'Piña')->where('is_active', true)->exists());
    }
}

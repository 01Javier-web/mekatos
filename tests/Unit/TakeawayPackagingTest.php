<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\TakeawayPackaging;
use PHPUnit\Framework\TestCase;

class TakeawayPackagingTest extends TestCase
{
    public function test_prepared_drinks_charge_packaging_when_taken_away(): void
    {
        foreach (['Jugo Natural Jarra', 'Milo Jarra', 'Granizado de Mora', 'Soda preparada', 'Tamarindo preparada (con limon)'] as $name) {
            $product = new Product(['name' => $name]);

            $this->assertSame(1500, TakeawayPackaging::fee($product, 1, 'PARA_LLEVAR'));
            $this->assertSame(3000, TakeawayPackaging::fee($product, 2, 'PARA_LLEVAR'));
        }
    }

    public function test_packaging_is_not_charged_for_table_orders(): void
    {
        $product = new Product(['name' => 'Jugo Natural Jarra']);

        $this->assertSame(0, TakeawayPackaging::fee($product, 2, 'MESA'));
    }

    public function test_delivery_orders_charge_packaging_like_takeaway_and_table_orders_do_not(): void
    {
        // Granizado, jugo y bebidas preparadas: $1.500 por unidad en PARA_LLEVAR y DOMICILIO, $0 en MESA.
        foreach (['Granizada de Mora', 'Granizado de Lulo', 'Cerezada', 'Jugo Natural Jarra', 'Limonada Jarra', 'Milo Jarra', 'Soda preparada', 'Tamarindo preparada (con limon)'] as $name) {
            $product = new Product(['name' => $name]);

            foreach (['PARA_LLEVAR', 'DOMICILIO'] as $type) {
                $this->assertSame(1500, TakeawayPackaging::fee($product, 1, $type), "{$name} en {$type}");
                $this->assertSame(4500, TakeawayPackaging::fee($product, 3, $type), "{$name} x3 en {$type}");
            }

            $this->assertSame(0, TakeawayPackaging::fee($product, 3, 'MESA'), "{$name} en MESA");
        }

        // Otros productos: $0 en cualquier tipo de pedido.
        foreach (['Hamburguesa Sencilla', 'Pollo Broaster', 'Gaseosa 350 ml', 'Jugos Hit', 'Cerveza'] as $name) {
            $product = new Product(['name' => $name]);

            foreach (['MESA', 'PARA_LLEVAR', 'DOMICILIO'] as $type) {
                $this->assertSame(0, TakeawayPackaging::fee($product, 2, $type), "{$name} en {$type}");
            }
        }
    }

    public function test_packaging_is_not_charged_for_other_beverages(): void
    {
        foreach (['Gaseosa 350 ml', 'Agua con gas 600 ml', 'H2O limón', 'Gatorade rojo', 'Jugos Hit', 'Cerveza'] as $name) {
            $product = new Product(['name' => $name]);

            $this->assertSame(0, TakeawayPackaging::fee($product, 2, 'PARA_LLEVAR'));
        }
    }
}

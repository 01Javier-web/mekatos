<?php

namespace App\Support;

use App\Models\Product;

class TakeawayPackaging
{
    public const COST_PER_UNIT = 1500;

    public static function applies(Product $product): bool
    {
        $name = mb_strtolower(trim($product->name));

        return in_array($name, [
            'jugo natural jarra',
            'limonada jarra',
            'milo jarra',
            'soda preparada',
            'tamarindo preparada',
            'tamarindo preparada (con limon)',
            'cerezada',
        ], true) || str_contains($name, 'granizada') || str_contains($name, 'granizado');
    }

    public static function fee(Product $product, int $quantity, string $orderType): int
    {
        // Icopor por unidad para PARA_LLEVAR y DOMICILIO; en MESA no se cobra.
        if (! in_array($orderType, ['PARA_LLEVAR', 'DOMICILIO'], true) || ! self::applies($product)) {
            return 0;
        }

        return self::COST_PER_UNIT * $quantity;
    }
}

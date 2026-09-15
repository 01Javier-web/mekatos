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
            'milo jarra',
            'soda preparada',
            'tamarindo preparada (con limon)',
            'tamarindo preparada',
        ], true) || str_contains($name, 'granizado');
    }

    public static function fee(Product $product, int $quantity, string $orderType): int
    {
        if ($orderType !== 'PARA_LLEVAR' || ! self::applies($product)) {
            return 0;
        }

        return self::COST_PER_UNIT * $quantity;
    }
}

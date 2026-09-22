<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ComboOptions
{
    public const PRICE = 10000;
    public const NO = 'NO';
    public const YES = 'SI';
    public const GASEOSA = 'GASEOSA';
    public const JUGO_HIT = 'JUGO_HIT';

    public static function isHamburger(Product $product): bool
    {
        return $product->category?->name === 'Hamburguesas';
    }

    public static function types(): array
    {
        return [
            self::GASEOSA => 'Gaseosa personal',
            self::JUGO_HIT => 'Jugo Hit',
        ];
    }

    public static function flavors(): array
    {
        return [
            self::GASEOSA => BeverageOptions::optionNames('Gaseosa 350 ml'),
            self::JUGO_HIT => BeverageOptions::optionNames('Jugos Hit'),
        ];
    }

    public static function availableFlavors(string $type): array
    {
        $type = strtoupper(trim($type));

        if ($type === self::JUGO_HIT) {
            return self::flavors()[self::JUGO_HIT];
        }

        if ($type !== self::GASEOSA) {
            return [];
        }

        $product = Product::query()
            ->where('name', 'Gaseosa 350 ml')
            ->first();

        if (! $product) {
            return self::flavors()[self::GASEOSA];
        }

        $available = BeverageOptions::availableFor($product);

        return $product->beverageOptions()->exists()
            ? $available
            : self::flavors()[self::GASEOSA];
    }

    public static function validate(Product $product, ?string $combo, ?string $type, ?string $flavor): array
    {
        if (! self::isHamburger($product)) {
            if (strtoupper(trim((string) $combo)) === self::YES) {
                throw ValidationException::withMessages([
                    'items' => ["El producto '{$product->name}' no admite combo de hamburguesa."],
                ]);
            }

            return ['combo' => self::NO, 'type' => null, 'flavor' => null];
        }

        $combo = strtoupper(trim((string) $combo));

        if ($combo === '' || $combo === self::NO) {
            return ['combo' => self::NO, 'type' => null, 'flavor' => null];
        }

        if ($combo !== self::YES) {
            throw ValidationException::withMessages([
                'items' => ["Selecciona si '{$product->name}' lleva combo."],
            ]);
        }

        $type = strtoupper(trim((string) $type));
        $flavor = trim((string) $flavor);

        if (! array_key_exists($type, self::types())) {
            throw ValidationException::withMessages([
                'items' => ["Selecciona la bebida del combo de '{$product->name}'."],
            ]);
        }

        $availableFlavors = self::availableFlavors($type);

        if (! in_array($flavor, $availableFlavors, true)) {
            throw ValidationException::withMessages([
                'items' => ["El sabor '{$flavor}' ya no está disponible para el combo."],
            ]);
        }

        return ['combo' => self::YES, 'type' => $type, 'flavor' => $flavor];
    }

    public static function buildNote(array $selection): string
    {
        if (($selection['combo'] ?? self::NO) !== self::YES) {
            return '';
        }

        $type = self::types()[$selection['type']] ?? $selection['type'];
        return 'COMBO +$10.000 · Papas a la francesa · '.$type.' · '.$selection['flavor'];
    }
}

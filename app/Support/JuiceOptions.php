<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class JuiceOptions
{
    public const PRODUCT_NAME = 'Jugo Natural Jarra';
    public const WATER = 'AGUA';
    public const MILK = 'LECHE';
    public const OTHER = 'OTRO';

    public const FRUITS = [
        'MARACUYA' => 'Maracuyá',
        'LULO' => 'Lulo',
        'MORA' => 'Mora',
        'FRESA' => 'Fresa',
        'OTRO' => 'Otro',
    ];

    public static function isJuice(Product $product): bool
    {
        return $product->name === self::PRODUCT_NAME;
    }

    public static function buildNote(?string $preparation, ?string $fruit, ?string $otherFruit = null, ?string $details = null): string
    {
        if (! in_array($preparation, [self::WATER, self::MILK], true)) {
            throw ValidationException::withMessages(['items' => ['Selecciona si el jugo natural es en agua o en leche.']]);
        }

        if (! array_key_exists($fruit, self::FRUITS)) {
            throw ValidationException::withMessages(['items' => ['Selecciona una fruta para el jugo natural.']]);
        }

        $preparationLabel = $preparation === self::WATER ? 'En agua' : 'En leche';
        if ($fruit === self::OTHER) {
            $otherFruit = trim((string) $otherFruit);
            if ($otherFruit === '') {
                throw ValidationException::withMessages(['items' => ['Si eliges "Otro", escribe la fruta en el apartado de detalles.']]);
            }
            return $preparationLabel.' · Otro: '.$otherFruit;
        }

        $note = $preparationLabel.' · '.self::FRUITS[$fruit];
        $details = trim((string) $details);
        return $details !== '' ? $note.' · '.$details : $note;
    }

    public static function price(?string $preparation): int
    {
        return $preparation === self::MILK ? 9500 : 8500;
    }
}

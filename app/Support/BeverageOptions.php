<?php

namespace App\Support;

use App\Models\BeverageOption;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class BeverageOptions
{
    public const PRODUCTS = [
        'Gaseosa 350 ml' => ['Coca-Cola', 'Colombiana', 'Manzana', 'Tamarindo'],
        'Gaseosa 400 ml' => ['Coca-Cola', 'Coca-Cola Zero', 'Quatro', 'Soda'],
        'Gaseosa 1.5 L' => ['Quatro', 'Colombiana', 'Manzana', 'Ginger', 'Coca-Cola'],
        'Jugos Hit' => ['Frutos tropicales', 'Naranja piña', 'Mora', 'Mango'],
        'Cerveza' => ['Águila Light', 'Poker'],
    ];

    public static function hasOptions(Product $product): bool
    {
        return $product->beverageOptions()->exists();
    }

    public static function optionNames(string $productName): array
    {
        return self::PRODUCTS[$productName] ?? [];
    }

    public static function availableFor(Product $product): array
    {
        return BeverageOption::query()
            ->where('product_id', $product->id)
            ->where('is_available', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();
    }

    public static function validateSelection(Product $product, ?string $option): string
    {
        $option = trim((string) $option);

        if (! self::hasOptions($product)) {
            if ($option !== '') {
                throw ValidationException::withMessages([
                    'items' => ['El producto seleccionado no admite una opción de bebida.'],
                ]);
            }

            return '';
        }

        if ($option === '') {
            throw ValidationException::withMessages([
                'items' => ["Selecciona una opción para '{$product->name}'."],
            ]);
        }

        $available = BeverageOption::query()
            ->where('product_id', $product->id)
            ->where('name', $option)
            ->where('is_available', true)
            ->exists();

        if (! $available) {
            throw ValidationException::withMessages([
                'items' => ["La opción '{$option}' para '{$product->name}' ya no está disponible."],
            ]);
        }

        return $option;
    }

    public static function buildNote(Product $product, ?string $option, ?string $details = null): ?string
    {
        $option = self::validateSelection($product, $option);
        $details = trim((string) $details);

        if ($option === '') {
            return $details !== '' ? $details : null;
        }

        return $details !== '' ? $option.' · '.$details : $option;
    }
}

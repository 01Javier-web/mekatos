<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Opciones del producto por unidad (jugos, opciones de bebida y combos), separadas de las salsas.
 *
 * Formato del formulario:
 * - option_mode[product_id] = same | each              ("Todos iguales" / "Personalizar individualmente")
 * - modo same: los campos de siempre por producto (juice_preparation[pid], juice_fruit[pid],
 *   juice_other_fruit[pid], beverage_option[pid], combo[pid], combo_beverage_type[pid],
 *   combo_beverage_flavor[pid]).
 * - modo each: unit_options[product_id][unidad][campo] con esos mismos campos (unidad 0..cantidad-1).
 *
 * El precio y la nota de cada unidad se calculan en el backend con los validadores de siempre
 * (JuiceOptions, BeverageOptions, ComboOptions). Las unidades con la misma nota, el mismo precio y
 * las mismas salsas se agrupan en una línea ×N; las distintas quedan en líneas independientes.
 */
class OrderUnitOptions
{
    public const MODE_SAME = 'same';

    public const MODE_EACH = 'each';

    private const FIELDS = ['juice_preparation', 'juice_fruit', 'juice_other_fruit', 'beverage_option', 'combo', 'combo_beverage_type', 'combo_beverage_flavor'];

    public static function rules(): array
    {
        return [
            'option_mode' => ['nullable', 'array'],
            'option_mode.*' => ['nullable', Rule::in([self::MODE_SAME, self::MODE_EACH])],
            'unit_options' => ['nullable', 'array'],
            'unit_options.*' => ['nullable', 'array'],
            'unit_options.*.*' => ['nullable', 'array'],
            'unit_options.*.*.juice_preparation' => ['nullable', Rule::in([JuiceOptions::WATER, JuiceOptions::MILK])],
            'unit_options.*.*.juice_fruit' => ['nullable', Rule::in(array_keys(JuiceOptions::FRUITS))],
            'unit_options.*.*.juice_other_fruit' => ['nullable', 'string', 'max:100'],
            'unit_options.*.*.beverage_option' => ['nullable', 'string', 'max:100'],
            'unit_options.*.*.combo' => ['nullable', Rule::in([ComboOptions::NO, ComboOptions::YES])],
            'unit_options.*.*.combo_beverage_type' => ['nullable', Rule::in(array_keys(ComboOptions::types()))],
            'unit_options.*.*.combo_beverage_flavor' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Reparte la cantidad de un producto en líneas: cada unidad con su precio, nota y salsas;
     * las idénticas se agrupan, en el orden de la primera unidad que usa cada configuración.
     *
     * @return array<int, array{quantity: int, price: int, notes: ?string, sauces: array<int, string>}>
     */
    public static function groups(Product $product, int $quantity, array $validated, int $productId): array
    {
        $each = ($validated['option_mode'][$productId] ?? self::MODE_SAME) === self::MODE_EACH && $quantity > 1;
        $shared = $each ? null : self::lineConfig($product, self::sharedSource($validated, $productId), $validated['item_notes'][$productId] ?? null);
        $groups = [];

        for ($unit = 0; $unit < $quantity; $unit++) {
            $config = $shared ?? self::lineConfig(
                $product,
                self::unitSource($validated, $productId, $unit),
                $validated['item_notes'][$productId] ?? null,
                $unit + 1,
            );
            $sauces = OrderSauces::unitSauces($validated, $productId, $unit);
            $key = json_encode([$config['price'], $config['notes'], $sauces]);

            if (! isset($groups[$key])) {
                $groups[$key] = ['quantity' => 0, 'price' => $config['price'], 'notes' => $config['notes'], 'sauces' => $sauces];
            }
            $groups[$key]['quantity']++;
        }

        return array_values($groups);
    }

    /**
     * Precio y nota de una unidad (o de todas, en "Todos iguales"), con los validadores de siempre.
     *
     * @return array{price: int, notes: ?string}
     */
    public static function lineConfig(Product $product, array $source, ?string $notes, ?int $unit = null): array
    {
        $price = (int) $product->price;
        $label = $unit !== null ? " (unidad {$unit})" : '';

        try {
            if (JuiceOptions::isJuice($product)) {
                $prep = $source['juice_preparation'] ?? null;
                $fruit = $source['juice_fruit'] ?? null;
                $other = $source['juice_other_fruit'] ?? null;
                $details = $notes;
                if ($fruit === JuiceOptions::OTHER && trim((string) $other) === '') {
                    $other = $details;
                    $details = null;
                }
                $price = JuiceOptions::price($prep);
                $notes = JuiceOptions::buildNote($prep, $fruit, $other, $details);
            } elseif (BeverageOptions::hasOptions($product)) {
                $notes = BeverageOptions::buildNote($product, $source['beverage_option'] ?? null, $notes);
            } elseif (! empty($source['beverage_option'])) {
                throw ValidationException::withMessages([
                    'items' => ["El producto '{$product->name}' no admite una opción de bebida."],
                ]);
            }

            $combo = ComboOptions::validate(
                $product,
                $source['combo'] ?? ComboOptions::NO,
                $source['combo_beverage_type'] ?? null,
                $source['combo_beverage_flavor'] ?? null
            );
        } catch (ValidationException $e) {
            // Mismo mensaje de siempre, indicando la unidad cuando se personaliza.
            throw ValidationException::withMessages(collect($e->errors())->map(
                fn (array $messages) => array_map(fn (string $m): string => $m.$label, $messages)
            )->all());
        }

        if ($combo['combo'] === ComboOptions::YES) {
            $price += ComboOptions::PRICE;
            $notes = trim(implode(' · ', array_filter([ComboOptions::buildNote($combo), $notes])));
        }

        return ['price' => $price, 'notes' => $notes];
    }

    /** Opciones compartidas ("Todos iguales"): los campos de siempre por producto. */
    private static function sharedSource(array $validated, int $productId): array
    {
        $source = [];
        foreach (self::FIELDS as $field) {
            $source[$field] = $validated[$field][$productId] ?? null;
        }

        return $source;
    }

    /** Opciones de una unidad ("Personalizar individualmente"). */
    private static function unitSource(array $validated, int $productId, int $unit): array
    {
        $unitOptions = $validated['unit_options'][$productId][$unit] ?? [];
        $source = [];
        foreach (self::FIELDS as $field) {
            $source[$field] = $unitOptions[$field] ?? null;
        }

        return $source;
    }
}

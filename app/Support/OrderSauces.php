<?php

namespace App\Support;

use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderRound;
use App\Models\OrderSauce;
use App\Models\Sauce;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Salsas de los pedidos PARA_LLEVAR y DOMICILIO (formularios de caja y del mesero).
 *
 * Formato del formulario:
 * - sauce_mode[product_id] = same | each              ("Todos iguales" / "Personalizar individualmente")
 * - sauces[product_id][sauce_id] = EN_PRODUCTO | APARTE              (modo same; vacío = no seleccionada)
 * - unit_sauces[product_id][unidad][sauce_id] = EN_PRODUCTO | APARTE (modo each; unidad 0..cantidad-1)
 * - general_sauces[] = sauce_id                                      (siempre APARTE)
 *
 * En modo each, las unidades con la misma configuración se agrupan en una sola línea
 * (order_item ×N) y cada configuración distinta queda en su propia línea con sus salsas.
 * Las salsas son gratuitas y sin cantidad: no tocan subtotal, total, icopor ni pagos.
 */
class OrderSauces
{
    public const MODE_SAME = 'same';

    public const MODE_EACH = 'each';

    public static function rules(): array
    {
        $placements = Rule::in(array_keys(OrderSauce::PLACEMENTS));

        return [
            'sauce_mode' => ['nullable', 'array'],
            'sauce_mode.*' => ['nullable', Rule::in([self::MODE_SAME, self::MODE_EACH])],
            'sauces' => ['nullable', 'array'],
            'sauces.*' => ['nullable', 'array'],
            'sauces.*.*' => ['nullable', $placements],
            'unit_sauces' => ['nullable', 'array'],
            'unit_sauces.*' => ['nullable', 'array'],
            'unit_sauces.*.*' => ['nullable', 'array'],
            'unit_sauces.*.*.*' => ['nullable', $placements],
            'general_sauces' => ['nullable', 'array'],
            'general_sauces.*' => ['integer', 'distinct'],
        ];
    }

    public static function allowedFor(?OrderType $type): bool
    {
        return in_array($type, [OrderType::TAKEAWAY, OrderType::DELIVERY], true);
    }

    /**
     * Reparte la cantidad de un producto en grupos con la misma configuración de salsas.
     * Modo same: un solo grupo con todas las unidades. Modo each: un grupo por
     * configuración distinta, en el orden de la primera unidad que la usa.
     *
     * @return array<int, array{quantity: int, sauces: array<int, string>}>
     */
    public static function unitGroups(int $quantity, array $validated, int $productId): array
    {
        $mode = $validated['sauce_mode'][$productId] ?? self::MODE_SAME;

        if ($mode !== self::MODE_EACH) {
            return [['quantity' => $quantity, 'sauces' => self::selected($validated['sauces'][$productId] ?? [])]];
        }

        $units = $validated['unit_sauces'][$productId] ?? [];
        $groups = [];

        for ($unit = 0; $unit < $quantity; $unit++) {
            $sauces = self::selected($units[$unit] ?? []);
            $key = json_encode($sauces);

            if (! isset($groups[$key])) {
                $groups[$key] = ['quantity' => 0, 'sauces' => $sauces];
            }
            $groups[$key]['quantity']++;
        }

        return array_values($groups);
    }

    /**
     * Rechaza salsas enviadas para productos que no están en el pedido.
     *
     * @param  array<int|string, int>  $items  product_id => cantidad
     */
    public static function assertOnlyOrderedProducts(array $items, array $validated): void
    {
        $withSauces = [];
        foreach ($validated['sauces'] ?? [] as $productId => $placements) {
            if (self::selected($placements ?? []) !== []) {
                $withSauces[] = (int) $productId;
            }
        }
        foreach ($validated['unit_sauces'] ?? [] as $productId => $units) {
            foreach ((array) $units as $placements) {
                if (self::selected($placements ?? []) !== []) {
                    $withSauces[] = (int) $productId;
                }
            }
        }

        $ordered = array_map('intval', array_keys($items));
        if (array_diff($withSauces, $ordered) !== []) {
            throw ValidationException::withMessages(['sauces' => ['Las salsas deben corresponder a un producto incluido en el pedido.']]);
        }
    }

    /**
     * Guarda las salsas de una ronda (pedido nuevo o adición).
     *
     * @param  array<int, array{0: OrderItem, 1: array<int, string>}>  $lineSauces  [línea creada, [sauce_id => ubicación]]
     */
    public static function attach(Order $order, OrderRound $round, array $lineSauces, array $generalSauces): void
    {
        $general = array_values(array_unique(array_map('intval', $generalSauces)));
        $sauceIds = $general;
        foreach ($lineSauces as [, $placements]) {
            $sauceIds = array_merge($sauceIds, array_keys($placements));
        }
        $sauceIds = array_values(array_unique($sauceIds));

        if ($sauceIds === []) {
            return;
        }

        if (! self::allowedFor($order->type)) {
            throw ValidationException::withMessages([
                'sauces' => ['Las salsas solo están disponibles para pedidos PARA LLEVAR y DOMICILIO.'],
            ]);
        }

        $sauces = Sauce::query()->whereIn('id', $sauceIds)->get()->keyBy('id');

        foreach ($sauceIds as $sauceId) {
            $sauce = $sauces->get($sauceId);
            if (! $sauce) {
                throw ValidationException::withMessages(['sauces' => ['La salsa seleccionada no existe.']]);
            }
            if (! $sauce->is_active) {
                throw ValidationException::withMessages(['sauces' => ["La salsa {$sauce->name} ya no está disponible."]]);
            }
        }

        foreach ($lineSauces as [$item, $placements]) {
            foreach ($placements as $sauceId => $placement) {
                OrderSauce::create([
                    'order_id' => $order->id,
                    'order_item_id' => $item->id,
                    'order_round_id' => $round->id,
                    'sauce_id' => $sauceId,
                    'placement' => $placement,
                ]);
            }
        }

        foreach ($general as $sauceId) {
            OrderSauce::create([
                'order_id' => $order->id,
                'order_item_id' => null,
                'order_round_id' => $round->id,
                'sauce_id' => $sauceId,
                'placement' => OrderSauce::ON_SIDE,
            ]);
        }
    }

    /**
     * Salsas elegidas (sin las vacías), ordenadas por id para comparar configuraciones.
     *
     * @return array<int, string>
     */
    private static function selected(mixed $placements): array
    {
        $selected = [];
        foreach ((array) $placements as $sauceId => $placement) {
            if ($placement !== null && $placement !== '') {
                $selected[(int) $sauceId] = (string) $placement;
            }
        }
        ksort($selected);

        return $selected;
    }
}

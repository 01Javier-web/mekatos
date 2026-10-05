<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Support\BeverageOptions;
use App\Support\ComboOptions;
use App\Support\JuiceOptions;
use App\Support\TableSessionLock;
use App\Support\TakeawayPackaging;
use App\TableSessionStatus;
use App\TableStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'type' => ['nullable', Rule::enum(OrderType::class)],
            'table_session_id' => ['nullable', 'integer', 'exists:table_sessions,id'],
            'table_token' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.juice_preparation' => ['nullable', Rule::in([JuiceOptions::WATER, JuiceOptions::MILK])],
            'items.*.juice_fruit' => ['nullable', Rule::in(array_keys(JuiceOptions::FRUITS))],
            'items.*.juice_other_fruit' => ['nullable', 'string', 'max:100'],
            'items.*.juice_options' => ['nullable', 'array'],
            'items.*.beverage_option' => ['nullable', 'string', 'max:100'],
            'items.*.combo' => ['nullable', Rule::in([ComboOptions::NO, ComboOptions::YES])],
            'items.*.combo_beverage_type' => ['nullable', Rule::in(array_keys(ComboOptions::types()))],
            'items.*.combo_beverage_flavor' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'delivery_reference' => ['nullable', 'string', 'max:255'],
            'delivery_fee' => ['nullable', 'regex:/^\\d+$/', 'max:9999999999'],
        ]);

        $type = OrderType::from($validatedData['type'] ?? OrderType::TABLE->value);
        $tableSessionId = $validatedData['table_session_id'] ?? null;
        $tableToken = $validatedData['table_token'] ?? $validatedData['token'] ?? null;

        // El endpoint es público: la mesa solo se identifica con el token de su QR.
        // Un table_session_id sin el token correcto no basta, porque los IDs son
        // consecutivos y se podrían adivinar.
        if ($type === OrderType::TABLE && ! $tableToken) {
            throw ValidationException::withMessages(['table_token' => ['Los pedidos en mesa requieren el código QR de la mesa.']]);
        }
        if ($type !== OrderType::TABLE && $tableSessionId) {
            throw ValidationException::withMessages(['table_session_id' => ['Un pedido para llevar no puede estar asociado a una mesa.']]);
        }
        if (in_array($type, [OrderType::TAKEAWAY, OrderType::DELIVERY], true) && ! $request->user()) {
            return response()->json(['message' => 'Los pedidos para llevar o a domicilio desde la API requieren autenticación.'], 401);
        }

        if ($type === OrderType::DELIVERY) {
            validator($validatedData, [
                'customer_name' => ['required', 'string', 'max:255'],
                'customer_phone' => ['required', 'string', 'max:30'],
                'delivery_address' => ['required', 'string', 'max:255'],
                'delivery_fee' => ['required', 'regex:/^\\d+$/', 'max:9999999999'],
            ])->validate();
        }

        $order = DB::transaction(function () use ($validatedData, $type, $tableSessionId, $tableToken) {
            // La sesión de mesa y la ocupación se resuelven dentro de la misma transacción
            // que el pedido, con la mesa y la sesión bloqueadas (orden mesa → sesión, ver
            // TableSessionLock): el cobro de la mesa no puede cerrar la sesión mientras
            // se crea el pedido, y si algo falla no queda una mesa ocupada sin pedido.
            if ($type === OrderType::TABLE && $tableSessionId) {
                $session = TableSessionLock::lockSession($tableSessionId);
                if (! TableSessionLock::isActive($session)) {
                    throw ValidationException::withMessages(['table_session_id' => [TableSessionLock::SESSION_CLOSED]]);
                }
                if (! $session->restaurantTable || $session->restaurantTable->qr_token !== $tableToken) {
                    throw ValidationException::withMessages(['table_token' => ['La mesa no coincide con la sesión indicada.']]);
                }
            } elseif ($type === OrderType::TABLE) {
                $table = RestaurantTable::query()->where('qr_token', $tableToken)->lockForUpdate()->first();
                if (! $table) throw ValidationException::withMessages(['table_token' => ['La mesa indicada no existe.']]);
                $session = TableSessionLock::lockActiveSessionOf($table);
                if (! $session) $session = $table->tableSessions()->create(['status' => TableSessionStatus::Active, 'started_at' => now()]);
                $tableSessionId = $session->id;
                $table->update(['status' => TableStatus::OCCUPIED]);
            }

            $order = Order::create([
                'table_session_id' => $tableSessionId,
                'type' => $type,
                'status' => OrderStatus::PENDING,
                'subtotal' => 0,
                'packaging_fee' => 0,
                'delivery_fee' => (int) ($validatedData['delivery_fee'] ?? 0),
                'tax' => 0,
                'total' => 0,
                'customer_name' => $validatedData['customer_name'] ?? null,
                'customer_phone' => $validatedData['customer_phone'] ?? null,
                'delivery_address' => $validatedData['delivery_address'] ?? null,
                'delivery_reference' => $validatedData['delivery_reference'] ?? null,
                'notes' => $validatedData['notes'] ?? null,
                'handled_by_user_id' => Auth::id(),
            ]);
            $round = $order->rounds()->create(['number' => 1, 'created_by_user_id' => Auth::id()]);
            $subtotal = 0;
            $packagingFee = 0;

            foreach ($validatedData['items'] as $item) {
                $product = Product::query()->with(['category', 'beverageOptions'])->findOrFail($item['product_id']);
                if (! $product->is_available) {
                    throw ValidationException::withMessages(['items' => ["El producto '{$product->name}' no está disponible."]]);
                }
                if (! $product->category?->is_active) {
                    throw ValidationException::withMessages(['items' => ["El producto '{$product->name}' pertenece a una categoría deshabilitada."]]);
                }

                $unitPrice = (int) $product->price;
                $lineNotes = $item['notes'] ?? null;

                if (JuiceOptions::isJuice($product)) {
                    $juice = $item['juice_options'] ?? [];
                    $unitPrice = JuiceOptions::price($juice['preparation'] ?? $item['juice_preparation'] ?? null);
                    $lineNotes = JuiceOptions::buildNote(
                        $juice['preparation'] ?? $item['juice_preparation'] ?? null,
                        $juice['fruit'] ?? $item['juice_fruit'] ?? null,
                        $juice['otherFruit'] ?? $item['juice_other_fruit'] ?? null,
                        $item['notes'] ?? null
                    );
                } elseif (BeverageOptions::hasOptions($product)) {
                    $lineNotes = BeverageOptions::buildNote($product, $item['beverage_option'] ?? null, $item['notes'] ?? null);
                } elseif (! empty($item['beverage_option'])) {
                    throw ValidationException::withMessages(['items' => ["El producto '{$product->name}' no admite una opción de bebida."]]);
                }

                $combo = ComboOptions::validate(
                    $product,
                    $item['combo'] ?? ComboOptions::NO,
                    $item['combo_beverage_type'] ?? null,
                    $item['combo_beverage_flavor'] ?? null
                );

                if ($combo['combo'] === ComboOptions::YES) {
                    $unitPrice += ComboOptions::PRICE;
                    $comboNote = ComboOptions::buildNote($combo);
                    $lineNotes = trim(implode(' · ', array_filter([$comboNote, $lineNotes])));
                }

                $quantity = (int) $item['quantity'];
                $lineTotal = $unitPrice * $quantity;
                $packagingFee += TakeawayPackaging::fee($product, $quantity, $type->value);

                \App\Models\OrderItem::create([
                    'order_id' => $order->id,
                    'order_round_id' => $round->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $lineTotal,
                    'notes' => $lineNotes,
                    'sent_at' => null,
                ]);
                $subtotal += $lineTotal;
            }

            $order->update([
                'subtotal' => $subtotal,
                'packaging_fee' => $packagingFee,
                'tax' => 0,
                'total' => $subtotal + $packagingFee + (int) ($validatedData['delivery_fee'] ?? 0),
            ]);
            $order->statusHistories()->create([
                'previous_status' => null,
                'new_status' => OrderStatus::PENDING->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);

            return $order;
        });

        $order->load(['orderItems.product', 'statusHistories', 'tableSession.restaurantTable', 'handledBy']);
        return response()->json(['message' => 'Pedido creado exitosamente', 'order' => $order], 201);
    }
}

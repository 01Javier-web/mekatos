<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\Product;
use App\Models\TableSession;
use App\TableSessionStatus;
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = OrderType::from($validatedData['type'] ?? OrderType::TABLE->value);
        $tableSessionId = $validatedData['table_session_id'] ?? null;

        if ($type === OrderType::TABLE && ! $tableSessionId) {
            throw ValidationException::withMessages([
                'table_session_id' => ['Los pedidos en mesa requieren una sesión activa.'],
            ]);
        }

        if ($type === OrderType::TAKEAWAY && $tableSessionId) {
            throw ValidationException::withMessages([
                'table_session_id' => ['Un pedido para llevar no puede estar asociado a una mesa.'],
            ]);
        }

        if ($tableSessionId) {
            $session = TableSession::query()->findOrFail($tableSessionId);

            if ($session->status !== TableSessionStatus::Active) {
                throw ValidationException::withMessages([
                    'table_session_id' => ['La sesión de la mesa no está activa.'],
                ]);
            }
        }

        $order = DB::transaction(function () use ($validatedData, $type, $tableSessionId) {
            $order = Order::create([
                'table_session_id' => $tableSessionId,
                'type' => $type,
                'status' => OrderStatus::PENDING,
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'notes' => $validatedData['notes'] ?? null,
                'handled_by_user_id' => Auth::id(),
            ]);

            $subtotal = 0;

            foreach ($validatedData['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);

                if (! $product->is_available) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$product->name}' no está disponible."],
                    ]);
                }

                $lineTotal = $product->price * $item['quantity'];

                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            $order->update([
                'subtotal' => $subtotal,
                'tax' => 0,
                'total' => $subtotal,
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

        return response()->json([
            'message' => 'Pedido creado exitosamente',
            'order' => $order,
        ], 201);
    }

    public function deliver(Order $order): JsonResponse
    {
        if ($order->status !== OrderStatus::READY) {
            throw ValidationException::withMessages([
                'status' => ['El pedido debe estar en estado READY para poder entregarse.'],
            ]);
        }

        $previousStatus = $order->status;

        DB::transaction(function () use ($order, $previousStatus) {
            $order->update([
                'status' => OrderStatus::DELIVERED,
                'delivered_by_user_id' => Auth::id(),
                'delivered_at' => now(),
            ]);

            $order->statusHistories()->create([
                'previous_status' => $previousStatus->value,
                'new_status' => OrderStatus::DELIVERED->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);
        });

        $order->load(['orderItems.product', 'statusHistories', 'tableSession.restaurantTable', 'deliveredBy']);

        return response()->json([
            'message' => 'Pedido entregado exitosamente',
            'order' => $order,
        ]);
    }
}

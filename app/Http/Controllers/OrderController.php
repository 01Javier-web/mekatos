<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Enums\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'table_session_id' => [
                'required',
                'integer',
                'exists:table_sessions,id',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $order = DB::transaction(function () use ($validatedData) {

            // Crear el pedido inicialmente en estado PENDING
            $order = Order::create([
                'table_session_id' => $validatedData['table_session_id'],
                'status' => OrderStatus::PENDING,
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
            ]);

            $subtotal = 0;

            foreach ($validatedData['items'] as $item) {

                $product = Product::query()
                    ->findOrFail($item['product_id']);

                // Verificar que el producto esté disponible
                if (! $product->is_available) {
                    throw ValidationException::withMessages([
                        'items' => [
                            "El producto '{$product->name}' no está disponible."
                        ],
                    ]);
                }

                // Calcular total de la línea
                $lineTotal = $product->price * $item['quantity'];

                // Crear detalle del pedido
                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total' => $lineTotal,
                ]);

                $subtotal += $lineTotal;
            }

            // Actualizar totales del pedido
            $order->update([
                'subtotal' => $subtotal,
                'tax' => 0,
                'total' => $subtotal,
            ]);

            // Registrar el estado inicial en el historial
            $order->statusHistories()->create([
                'previous_status' => null,
                'new_status' => OrderStatus::PENDING->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);

            return $order;
        });

        // Cargar relaciones para devolver toda la información necesaria
        $order->load([
            'orderItems.product',
            'statusHistories',
            'tableSession.restaurantTable',
        ]);

        return response()->json([
            'message' => 'Pedido creado exitosamente',
            'order' => $order,
        ], 201);
    }

    public function deliver(Order $order): JsonResponse
    {
        // Verificar que el pedido esté listo para entregar
        if ($order->status !== OrderStatus::READY) {
            throw ValidationException::withMessages([
                'status' => [
                    'El pedido debe estar en estado READY para poder entregarse.'
                ],
            ]);
        }

        $previousStatus = $order->status;

        DB::transaction(function () use ($order, $previousStatus) {

            // Cambiar el estado a DELIVERED
            $order->update([
                'status' => OrderStatus::DELIVERED,
                'delivered_by_user_id' => Auth::id(),
                'delivered_at' => now(),
            ]);

            // Registrar el cambio en el historial
            $order->statusHistories()->create([
                'previous_status' => $previousStatus->value,
                'new_status' => OrderStatus::DELIVERED->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);
        });

        // Cargar relaciones
        $order->load([
            'orderItems.product',
            'statusHistories',
            'tableSession.restaurantTable',
            'deliveredBy',
        ]);

        return response()->json([
            'message' => 'Pedido entregado exitosamente',
            'order' => $order,
        ]);
    }
}
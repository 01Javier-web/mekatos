<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Enums\OrderStatus;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()
            ->with(['tableSession.restaurantTable', 'orderItems.product', 'statusHistories', 'handledBy', 'deliveredBy', 'paidBy'])
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()->paginate(10);
        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['tableSession.restaurantTable', 'orderItems.product', 'statusHistories', 'handledBy', 'deliveredBy', 'paidBy']);
        return response()->json($order);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]]);
        $newStatus = OrderStatus::from($validated['status']);
        if ($order->status !== OrderStatus::PENDING || $newStatus !== OrderStatus::PREPARING) {
            throw ValidationException::withMessages(['status' => ['El cambio a EN PREPARACIÓN se realiza al imprimir las comandas del pedido.']]);
        }
        DB::transaction(function () use ($order, $newStatus) {
            $previousStatus = $order->status;
            $order->update(['status' => $newStatus]);
            $order->statusHistories()->create(['previous_status' => $previousStatus->value, 'new_status' => $newStatus->value, 'changed_by_user_id' => Auth::id(), 'changed_at' => now()]);
        });
        $order->load(['tableSession.restaurantTable', 'orderItems.product', 'statusHistories', 'handledBy', 'deliveredBy', 'paidBy']);
        return response()->json(['message' => 'Estado del pedido actualizado exitosamente', 'order' => $order]);
    }
}

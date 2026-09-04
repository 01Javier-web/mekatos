<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->with(['tableSession.restaurantTable', 'orderItems.product'])
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->get();

        return view('admin.orders.index', [
            'orders' => $orders,
            'statuses' => OrderStatus::cases(),
            'selectedStatus' => $request->status,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'tableSession.restaurantTable',
            'orderItems.product',
            'statusHistories.changedBy',
            'handledBy',
            'deliveredBy',
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'statuses' => OrderStatus::cases(),
        ]);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
        ]);

        $newStatus = OrderStatus::from($validated['status']);
        $currentStatus = $order->status;

        $allowedTransitions = [
            OrderStatus::PENDING->value => [OrderStatus::PREPARING, OrderStatus::CANCELLED],
            OrderStatus::PREPARING->value => [OrderStatus::READY, OrderStatus::CANCELLED],
            OrderStatus::READY->value => [OrderStatus::DELIVERED],
            OrderStatus::DELIVERED->value => [],
            OrderStatus::CANCELLED->value => [],
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus->value] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["No se puede cambiar el pedido de {$currentStatus->value} a {$newStatus->value}."],
            ]);
        }

        DB::transaction(function () use ($order, $currentStatus, $newStatus) {
            $order->update(['status' => $newStatus]);
            $order->statusHistories()->create([
                'previous_status' => $currentStatus->value,
                'new_status' => $newStatus->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);
        });

        return redirect()->route('admin.orders.show', $order)->with('success', 'Estado del pedido actualizado exitosamente.');
    }

    public function deliver(Order $order): RedirectResponse
    {
        if ($order->status !== OrderStatus::READY) {
            throw ValidationException::withMessages([
                'status' => ['El pedido debe estar en estado LISTO para poder entregarse.'],
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

        return redirect()->route('admin.orders.show', $order)->with('success', 'Pedido entregado exitosamente.');
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\View\View;

class WaiterController extends Controller
{
    public function index(): View
    {
        $orders = Order::query()
            ->with(['tableSession.restaurantTable', 'orderItems.product', 'orderItems.sauces.sauce', 'generalSauces.sauce', 'dispatchedBy'])
            // Pedidos activos: PENDIENTE y POR COBRAR (incluye los estados heredados). Los
            // TERMINADOS y CANCELADOS ya no se muestran.
            ->whereIn('status', OrderStatus::activeValues())
            ->latest()
            ->get();

        $countOf = fn (OrderStatus $status): int => $orders->filter(fn (Order $order): bool => $order->status->operational() === $status)->count();

        return view('waiter.orders', [
            'orders' => $orders,
            'counts' => [
                'total' => $orders->count(),
                'pending' => $countOf(OrderStatus::PENDING),
                'to_collect' => $countOf(OrderStatus::TO_COLLECT),
            ],
        ]);
    }
}

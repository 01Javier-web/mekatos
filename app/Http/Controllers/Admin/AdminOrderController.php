<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $request->validate(['status' => ['required', Rule::enum(OrderStatus::class)]]);

        // Ya no hay cambios manuales de estado (igual que en la web): PENDIENTE → ENTREGADO
        // ocurre al imprimir las comandas.
        throw ValidationException::withMessages(['status' => ['El estado del pedido no se cambia manualmente: pasa a ENTREGADO al imprimir las comandas.']]);
    }
}

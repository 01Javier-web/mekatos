<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\TableSessionStatus;
use App\TableStatus;
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

    public function create(): View
    {
        return view('admin.orders.create', [
            'products' => Product::query()->with('category')->where('is_available', true)->orderBy('name')->get(),
            'tables' => RestaurantTable::query()->where('status', '!=', TableStatus::CLEANING->value)->orderBy('number')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'orderTypes' => OrderType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(OrderType::class)],
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $type = OrderType::from($validated['type']);

        if ($type === OrderType::TABLE && empty($validated['table_id'])) {
            throw ValidationException::withMessages([
                'table_id' => ['Selecciona una mesa para un pedido en mesa.'],
            ]);
        }

        if ($type === OrderType::TAKEAWAY && ! empty($validated['table_id'])) {
            throw ValidationException::withMessages([
                'table_id' => ['Un pedido para llevar no puede tener una mesa asociada.'],
            ]);
        }

        $order = DB::transaction(function () use ($validated, $type) {
            $tableSession = null;

            if ($type === OrderType::TABLE) {
                $table = RestaurantTable::query()->lockForUpdate()->findOrFail($validated['table_id']);

                if ($table->status === TableStatus::CLEANING) {
                    throw ValidationException::withMessages([
                        'table_id' => ['La mesa seleccionada no está disponible para recibir pedidos.'],
                    ]);
                }

                $tableSession = TableSession::query()
                    ->where('restaurant_table_id', $table->id)
                    ->where('status', TableSessionStatus::Active->value)
                    ->latest('id')
                    ->first();

                if (! $tableSession) {
                    $tableSession = TableSession::create([
                        'restaurant_table_id' => $table->id,
                        'status' => TableSessionStatus::Active,
                        'started_at' => now(),
                    ]);
                }

                $table->update(['status' => TableStatus::OCCUPIED]);
            }

            $order = Order::create([
                'table_session_id' => $tableSession?->id,
                'type' => $type,
                'status' => OrderStatus::PENDING,
                'subtotal' => 0,
                'tax' => 0,
                'total' => 0,
                'notes' => $validated['notes'] ?? null,
                'handled_by_user_id' => Auth::id(),
            ]);

            $subtotal = 0;

            foreach ($validated['items'] as $productId => $quantity) {
                $product = Product::query()->findOrFail($productId);

                if (! $product->is_available) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto '{$product->name}' no está disponible."],
                    ]);
                }

                $lineTotal = $product->price * $quantity;

                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
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

        $route = Auth::user()?->role?->value === 'MESERO' ? 'waiter.orders' : 'admin.orders.show';

        return redirect()->route($route, $route === 'admin.orders.show' ? $order : [])->with('success', "Pedido #{$order->id} creado y enviado a cocina.");
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

        if (Auth::user()?->role?->value === 'MESERO') {
            return redirect()->route('waiter.orders')->with('success', "Pedido #{$order->id} actualizado a {$newStatus->value}.");
        }

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

        $route = Auth::user()?->role?->value === 'MESERO' ? 'waiter.orders' : 'admin.orders.show';

        return redirect()->route($route, $route === 'admin.orders.show' ? $order : [])->with('success', 'Pedido entregado exitosamente.');
    }
}

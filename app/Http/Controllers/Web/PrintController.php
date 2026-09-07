<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TableSession;
use App\TableSessionStatus;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class PrintController extends Controller
{
    public function orderPack(Order $order): View
    {
        if ($order->status !== OrderStatus::PENDING) {
            throw ValidationException::withMessages(['status' => ['Solo se pueden imprimir comandas de pedidos PENDIENTES.']]);
        }

        $previousStatus = $order->status;
        DB::transaction(function () use ($order, $previousStatus): void {
            $order->update(['status' => OrderStatus::PREPARING]);
            $order->statusHistories()->create([
                'previous_status' => $previousStatus->value,
                'new_status' => OrderStatus::PREPARING->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);
        });

        $order->load(['tableSession.restaurantTable', 'orderItems.product', 'handledBy']);
        $juiceItems = $order->orderItems->filter(fn ($item): bool =>
            str_starts_with(mb_strtolower($item->product?->name ?? ''), 'jugo')
        )->values();
        $kitchenItems = $order->orderItems->reject(fn ($item): bool =>
            str_starts_with(mb_strtolower($item->product?->name ?? ''), 'jugo')
        )->values();

        return view('print.order-pack', [
            'order' => $order,
            'kitchenItems' => $kitchenItems,
            'juiceItems' => $juiceItems,
        ]);
    }

    public function account(TableSession $tableSession): View
    {
        $this->loadActiveAccount($tableSession);
        $orders = $tableSession->orders;
        $total = $orders->sum('total');
        return view('admin.accounts.show', compact('tableSession', 'orders', 'total'));
    }

    public function printAccount(TableSession $tableSession): View
    {
        $this->loadActiveAccount($tableSession);
        $orders = $tableSession->orders;
        $total = $orders->sum('total');
        return view('print.account', compact('tableSession', 'orders', 'total'));
    }

    private function loadActiveAccount(TableSession $tableSession): void
    {
        $tableSession->load([
            'restaurantTable',
            'orders' => fn ($query) => $query->where('status', '!=', OrderStatus::COMPLETED)->with('orderItems.product')->orderBy('created_at'),
        ]);
        if ($tableSession->status !== TableSessionStatus::Active) {
            throw ValidationException::withMessages(['table' => ['La sesión de esta mesa ya está cerrada.']]);
        }
    }

    public function payTableSession(TableSession $tableSession): RedirectResponse
    {
        DB::transaction(function () use ($tableSession): void {
            $session = TableSession::query()->lockForUpdate()->with('restaurantTable')->findOrFail($tableSession->id);
            if ($session->status !== TableSessionStatus::Active) {
                throw ValidationException::withMessages(['table' => ['La cuenta de esta mesa ya está cerrada.']]);
            }

            $orders = $session->orders()->lockForUpdate()->where('status', '!=', OrderStatus::COMPLETED)->get();
            if ($orders->isEmpty()) {
                throw ValidationException::withMessages(['table' => ['No hay pedidos pendientes de cobro en esta mesa.']]);
            }
            if ($orders->contains(fn (Order $order): bool => $order->status !== OrderStatus::DELIVERED)) {
                throw ValidationException::withMessages(['status' => ['No se puede cerrar la cuenta mientras haya pedidos que todavía no estén ENTREGADOS.']]);
            }

            foreach ($orders as $order) {
                $order->update([
                    'status' => OrderStatus::COMPLETED,
                    'paid_at' => now(),
                    'paid_by_user_id' => Auth::id(),
                ]);
                $order->statusHistories()->create([
                    'previous_status' => OrderStatus::DELIVERED->value,
                    'new_status' => OrderStatus::COMPLETED->value,
                    'changed_by_user_id' => Auth::id(),
                    'changed_at' => now(),
                ]);
            }

            $session->update(['status' => TableSessionStatus::CLOSED, 'ended_at' => now()]);
            $session->restaurantTable?->update(['status' => TableStatus::AVAILABLE]);
        });

        return redirect()->route('waiter.orders')->with('success', 'Cuenta pagada. La mesa quedó disponible nuevamente.');
    }

    public function payOrder(Order $order): RedirectResponse
    {
        if ($order->type?->value !== 'PARA_LLEVAR') {
            throw ValidationException::withMessages(['order' => ['Los pedidos en mesa se cobran mediante la cuenta de la mesa.']]);
        }
        if ($order->status !== OrderStatus::DELIVERED) {
            throw ValidationException::withMessages(['status' => ['El pedido debe estar ENTREGADO antes de registrar el pago.']]);
        }

        DB::transaction(function () use ($order): void {
            $order->update(['status' => OrderStatus::COMPLETED, 'paid_at' => now(), 'paid_by_user_id' => Auth::id()]);
            $order->statusHistories()->create([
                'previous_status' => OrderStatus::DELIVERED->value,
                'new_status' => OrderStatus::COMPLETED->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);
        });

        return redirect()->route('waiter.orders')->with('success', 'Pago registrado. Pedido terminado.');
    }
}

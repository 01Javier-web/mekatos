<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TableSession;
use App\Support\TableSessionLock;
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
        if (! in_array($order->status->operational(), [OrderStatus::PENDING, OrderStatus::DELIVERED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden imprimir pedidos pendientes o con nuevas adiciones pendientes.'],
            ]);
        }

        $order->load([
            'tableSession.restaurantTable',
            'orderItems.product.category',
            'orderItems.pairedOrderItem.product',
            'handledBy',
        ]);

        $unsentItems = $order->orderItems
            ->filter(fn ($item): bool => $item->sent_at === null)
            ->values();

        if ($unsentItems->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => ['No hay productos nuevos pendientes de impresión en este pedido.'],
            ]);
        }

        $isAddition = $order->orderItems->contains(
            fn ($item): bool => $item->sent_at !== null
        );

        $previousStatus = $order->status;

        DB::transaction(function () use ($order, $unsentItems, $previousStatus): void {
            // La impresión operativa es la que entrega el pedido: PENDIENTE → ENTREGADO.
            // Quien imprime queda como responsable de la entrega (reporte de entregas).
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
                'notes' => $previousStatus === OrderStatus::PENDING
                    ? 'Comandas impresas.'
                    : 'Nueva adición impresa.',
            ]);

            // PARA_LLEVAR: la impresión incluye el "Pedido completo" con el TOTAL (su cuenta),
            // así que, como al imprimir la cuenta de una mesa, queda ENTREGADO → POR COBRAR.
            if ($order->type === OrderType::TAKEAWAY) {
                $order->update(['status' => OrderStatus::TO_COLLECT]);

                $order->statusHistories()->create([
                    'previous_status' => OrderStatus::DELIVERED->value,
                    'new_status' => OrderStatus::TO_COLLECT->value,
                    'changed_by_user_id' => Auth::id(),
                    'changed_at' => now(),
                    'notes' => 'Pedido para llevar impreso con total.',
                ]);
            }

            // Todos los productos de una misma impresión comparten la misma marca de
            // tiempo; así la reimpresión puede identificar exactamente ese envío.
            $sentAt = now();
            $unsentItems->each(fn ($item) => $item->update(['sent_at' => $sentAt]));
        });

        $beverageItems = $unsentItems
            ->filter(fn ($item): bool => $this->isPreparedByBeverageStation($item))
            ->values();

        $kitchenItems = $unsentItems
            ->reject(fn ($item): bool => $this->isBeverageItem($item))
            ->values();

        $printItems = $isAddition ? $unsentItems : $order->orderItems->values();

        $freshOrder = $order->fresh(['tableSession.restaurantTable', 'handledBy', 'orderItems.product.category', 'orderItems.pairedOrderItem.product', 'rounds.createdBy']);
        $latestRound = $freshOrder->rounds->sortByDesc('number')->first();
        $roundNumber = (int) ($latestRound?->number ?? 1);
        $roundCreatedBy = $latestRound?->createdBy?->name;

        return view('print.order-pack', [
            'order' => $freshOrder,
            'kitchenItems' => $kitchenItems,
            'beverageItems' => $beverageItems,
            'takeawayItems' => $printItems,
            'isAddition' => $isAddition,
            'roundNumber' => $roundNumber,
            'roundCreatedBy' => $roundCreatedBy,
        ]);
    }

    /**
     * Reimprime la última comanda ya enviada (por ejemplo, si falló la impresora).
     * Es de solo lectura: no cambia sent_at, el estado, las rondas ni el historial.
     */
    public function reprintOrder(Order $order): View
    {
        $order->load([
            'tableSession.restaurantTable',
            'orderItems.product.category',
            'orderItems.pairedOrderItem.product',
            'handledBy',
            'rounds.createdBy',
        ]);

        $sentItems = $order->orderItems
            ->filter(fn ($item): bool => $item->sent_at !== null)
            ->values();

        if ($sentItems->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => ['Este pedido todavía no tiene comandas enviadas para reimprimir.'],
            ]);
        }

        // sent_at se guarda como 'Y-m-d H:i:s' (sin cast en el modelo), por lo que
        // la comparación de texto respeta el orden cronológico.
        $lastSentAt = (string) $sentItems->max(fn ($item): string => (string) $item->sent_at);

        $lastBatch = $sentItems
            ->filter(fn ($item): bool => (string) $item->sent_at === $lastSentAt)
            ->values();

        $isAddition = $sentItems->contains(
            fn ($item): bool => (string) $item->sent_at < $lastSentAt
        );

        $batchRoundIds = $lastBatch->pluck('order_round_id')->filter()->unique();
        $latestRound = $order->rounds
            ->whereIn('id', $batchRoundIds)
            ->sortByDesc('number')
            ->first() ?? $order->rounds->sortByDesc('number')->first();

        return view('print.order-pack', [
            'order' => $order,
            'kitchenItems' => $lastBatch->reject(fn ($item): bool => $this->isBeverageItem($item))->values(),
            'beverageItems' => $lastBatch->filter(fn ($item): bool => $this->isPreparedByBeverageStation($item))->values(),
            'takeawayItems' => $isAddition ? $lastBatch : $sentItems,
            'isAddition' => $isAddition,
            'roundNumber' => (int) ($latestRound?->number ?? 1),
            'roundCreatedBy' => $latestRound?->createdBy?->name,
            'isReprint' => true,
        ]);
    }

    private function isBeverageItem($item): bool
    {
        $productName = mb_strtolower(trim($item->product?->name ?? ''));
        $category = mb_strtolower(trim($item->product?->category?->name ?? ''));

        if ($this->isPreparedByWaiter($productName)) {
            return true;
        }

        if (in_array($category, [
            'jugos y bebidas preparadas',
            'gaseosas y agua',
            'cerveza',
            'granizadas',
        ], true)) {
            return true;
        }

        return str_contains($productName, 'gaseosa')
            || str_contains($productName, 'agua')
            || str_contains($productName, 'cerveza')
            || str_contains($productName, 'jugo')
            || str_contains($productName, 'limonada')
            || str_contains($productName, 'milo')
            || str_contains($productName, 'granizada')
            || str_contains($productName, 'cerezada');
    }

    private function isPreparedByBeverageStation($item): bool
    {
        $productName = mb_strtolower(trim($item->product?->name ?? ''));
        $category = mb_strtolower(trim($item->product?->category?->name ?? ''));

        if ($this->isPreparedByWaiter($productName)) {
            return false;
        }

        return in_array($category, [
            'jugos y bebidas preparadas',
            'granizadas',
        ], true);
    }

    private function isPreparedByWaiter(string $productName): bool
    {
        return in_array($productName, [
            'soda preparada',
            'tamarindo preparada',
            'tamarindo preparada (con limon)',
        ], true);
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
        $this->markAccountToCollect($tableSession);
        $orders = $tableSession->orders;
        $total = $orders->sum('total');

        return view('print.account', compact('tableSession', 'orders', 'total'));
    }

    /**
     * Al imprimir la cuenta, los pedidos ya entregados de la mesa pasan a POR COBRAR
     * (con su historial). No cambia ningún otro dato de la cuenta; los pedidos que
     * siguen PENDIENTES o que ya están POR COBRAR no se tocan.
     */
    private function markAccountToCollect(TableSession $tableSession): void
    {
        DB::transaction(function () use ($tableSession): void {
            // Orden de bloqueo mesa → sesión → pedidos (ver TableSessionLock).
            $session = TableSessionLock::lockSession($tableSession->id);

            if (! TableSessionLock::isActive($session)) {
                return;
            }

            $orders = $session->orders()
                ->lockForUpdate()
                ->where('status', '!=', OrderStatus::COMPLETED->value)
                ->get();

            foreach ($orders as $order) {
                if ($order->status->operational() !== OrderStatus::DELIVERED) {
                    continue;
                }

                $previousStatus = $order->status;
                $order->update(['status' => OrderStatus::TO_COLLECT]);

                $order->statusHistories()->create([
                    'previous_status' => $previousStatus->value,
                    'new_status' => OrderStatus::TO_COLLECT->value,
                    'changed_by_user_id' => Auth::id(),
                    'changed_at' => now(),
                    'notes' => 'Cuenta impresa.',
                ]);
            }
        });
    }

    private function loadActiveAccount(TableSession $tableSession): void
    {
        $tableSession->load([
            'restaurantTable',
            'orders' => fn ($query) => $query
                ->where('status', '!=', OrderStatus::COMPLETED->value)
                ->with('orderItems.product')
                ->orderBy('created_at'),
        ]);

        if ($tableSession->status !== TableSessionStatus::Active) {
            throw ValidationException::withMessages([
                'table' => ['La sesión de esta mesa ya está cerrada.'],
            ]);
        }
    }

    public function payTableSession(TableSession $tableSession): RedirectResponse
    {
        DB::transaction(function () use ($tableSession): void {
            // Orden de bloqueo mesa → sesión → pedidos (ver TableSessionLock), el mismo
            // que usan crear pedidos de mesa, agregar productos y el cierre del día.
            $session = TableSessionLock::lockSession($tableSession->id);

            if (! $session || $session->status !== TableSessionStatus::Active) {
                throw ValidationException::withMessages([
                    'table' => ['La cuenta de esta mesa ya está cerrada.'],
                ]);
            }

            $orders = $session->orders()
                ->lockForUpdate()
                ->where('status', '!=', OrderStatus::COMPLETED->value)
                ->get();

            if ($orders->isEmpty()) {
                throw ValidationException::withMessages([
                    'table' => ['No hay pedidos pendientes de cobro en esta mesa.'],
                ]);
            }

            // Se cobra desde ENTREGADO o POR COBRAR (y sus equivalentes heredados); nunca PENDIENTE.
            $notReady = $orders->filter(
                fn (Order $order): bool => ! $order->status->isCollectable()
            );

            if ($notReady->isNotEmpty()) {
                $blocked = $notReady->map(function (Order $order): string {
                    return '#'.$order->id.' ('.$order->status?->value.')';
                })->implode(', ');

                throw ValidationException::withMessages([
                    'status' => [
                        "No se puede cerrar la cuenta. Los siguientes pedidos todavía no han sido enviados a cocina: {$blocked}.",
                    ],
                ]);
            }

            foreach ($orders as $order) {
                $previousStatus = $order->status;

                $order->update([
                    'status' => OrderStatus::COMPLETED,
                    'paid_at' => now(),
                    'paid_by_user_id' => Auth::id(),
                ]);

                $order->statusHistories()->create([
                    'previous_status' => $previousStatus->value,
                    'new_status' => OrderStatus::COMPLETED->value,
                    'changed_by_user_id' => Auth::id(),
                    'changed_at' => now(),
                ]);
            }

            $session->update([
                'status' => TableSessionStatus::CLOSED,
                'ended_at' => now(),
            ]);

            $session->restaurantTable?->update([
                'status' => TableStatus::AVAILABLE,
            ]);
        });

        $route = Auth::user()?->role?->value === 'MESERO'
            ? 'waiter.orders'
            : 'admin.orders.index';

        return redirect()
            ->route($route)
            ->with('success', 'Cuenta pagada. La mesa quedó disponible nuevamente.');
    }

    public function payOrder(Order $order): RedirectResponse
    {
        if (! in_array($order->type?->value, ['PARA_LLEVAR', 'DOMICILIO'], true)) {
            throw ValidationException::withMessages([
                'order' => ['Los pedidos en mesa se cobran mediante la cuenta de la mesa.'],
            ]);
        }

        // PARA_LLEVAR y DOMICILIO se cobran desde ENTREGADO o POR COBRAR
        // (y sus equivalentes heredados); nunca PENDIENTE.
        if (! $order->status->isCollectable()) {
            throw ValidationException::withMessages([
                'status' => ['El pedido todavía no está listo para cerrar y registrar el pago.'],
            ]);
        }

        DB::transaction(function () use ($order): void {
            $previousStatus = $order->status;

            $order->update([
                'status' => OrderStatus::COMPLETED,
                'paid_at' => now(),
                'paid_by_user_id' => Auth::id(),
            ]);

            $order->statusHistories()->create([
                'previous_status' => $previousStatus->value,
                'new_status' => OrderStatus::COMPLETED->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
            ]);
        });

        $route = Auth::user()?->role?->value === 'MESERO'
            ? 'waiter.orders'
            : 'admin.orders.index';

        return redirect()
            ->route($route)
            ->with('success', 'Pago registrado. Pedido terminado.');
    }
}

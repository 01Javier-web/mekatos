<?php

namespace App\Http\Controllers\Web;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderSauce;
use App\Models\TableSession;
use App\Support\TableSessionLock;
use App\TableSessionStatus;
use App\TableStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class PrintController extends Controller
{
    public function orderPack(Order $order): View
    {
        [$order, $unsentItems, $voidItems, $isAddition, $newGeneral, $voidGeneral] = DB::transaction(function () use ($order): array {
            // El pedido se bloquea y todo se comprueba sobre la fila bloqueada: otra petición
            // pudo cancelarlo, imprimirlo, editarlo o agregarle productos mientras tanto.
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status->operational() !== OrderStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => ['Solo se pueden imprimir pedidos pendientes o con cambios pendientes de imprimir.'],
                ]);
            }

            $order->load([
                'tableSession.restaurantTable',
                'allOrderItems.product.category',
                'allOrderItems.pairedOrderItem.product',
                'allOrderItems.sauces.sauce',
                'allGeneralSauces.sauce',
                'handledBy',
            ]);

            // Salsas generales nuevas (sin enviar) y anuladas después de enviarse, sin aviso.
            $newGeneral = $order->allGeneralSauces->filter(fn ($s): bool => $s->voided_at === null && $s->sent_at === null)->values();
            $voidGeneral = $order->allGeneralSauces->filter(fn ($s): bool => $s->voided_at !== null && $s->void_sent_at === null)->values();

            // ✅ PREPARAR: líneas activas que todavía no se enviaron a cocina.
            $unsentItems = $order->allOrderItems
                ->filter(fn ($item): bool => ! $item->isVoided() && $item->sent_at === null)
                ->values();
            // ❌ NO PREPARAR: líneas ya enviadas que una edición anuló y cocina aún no sabe.
            // (Una línea anulada que nunca se envió no se imprime.)
            $voidItems = $order->allOrderItems
                ->filter(fn ($item): bool => $item->isVoided() && $item->sent_at !== null && $item->void_sent_at === null)
                ->values();

            if ($unsentItems->isEmpty() && $voidItems->isEmpty() && $newGeneral->isEmpty() && $voidGeneral->isEmpty()) {
                throw ValidationException::withMessages([
                    'status' => ['No hay cambios pendientes de impresión en este pedido.'],
                ]);
            }

            $isAddition = $order->allOrderItems->contains(
                fn ($item): bool => $item->sent_at !== null
            );

            $previousStatus = $order->status;

            // Imprimir las comandas deja el pedido POR COBRAR (en los tres tipos de pedido).
            // Quien imprime queda como responsable de la entrega (reporte de entregas); en
            // domicilios, "🛵 Salió" lo reemplaza por quien marca la salida.
            $order->update([
                'status' => OrderStatus::TO_COLLECT,
                'delivered_by_user_id' => Auth::id(),
                'delivered_at' => now(),
            ]);

            $order->statusHistories()->create([
                'previous_status' => $previousStatus->value,
                'new_status' => OrderStatus::TO_COLLECT->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
                'notes' => self::isChangeTicket($isAddition, $unsentItems, $voidItems, $newGeneral, $voidGeneral)
                    ? 'Comanda de cambio impresa.'
                    : ($isAddition ? 'Nueva adición impresa.' : 'Comandas impresas.'),
            ]);

            // Todos los productos de una misma impresión comparten la misma marca de
            // tiempo (también los "NO PREPARAR" y las salsas generales); así la reimpresión
            // identifica ese envío. La marca es estrictamente creciente dentro del pedido
            // (el pedido está bloqueado): dos impresiones nunca comparten el mismo segundo.
            $sentAt = $this->nextPrintStamp($order);
            $unsentItems->each(fn ($item) => $item->update(['sent_at' => $sentAt]));
            $voidItems->each(fn ($item) => $item->update(['void_sent_at' => $sentAt]));
            $newGeneral->each(fn ($sauce) => $sauce->update(['sent_at' => $sentAt]));
            $voidGeneral->each(fn ($sauce) => $sauce->update(['void_sent_at' => $sentAt]));

            return [$order, $unsentItems, $voidItems, $isAddition, $newGeneral, $voidGeneral];
        });

        $isChange = self::isChangeTicket($isAddition, $unsentItems, $voidItems, $newGeneral, $voidGeneral);
        // Comanda de cambio: el "pedido completo" sale actualizado con todo lo vigente.
        $printItems = $isChange
            ? $order->orderItems()->with(['product', 'sauces.sauce'])->get()
            : ($isAddition ? $unsentItems : $order->orderItems()->with(['product', 'sauces.sauce'])->get());

        $freshOrder = $order->fresh(['tableSession.restaurantTable', 'handledBy', 'orderItems.product.category', 'orderItems.pairedOrderItem.product', 'rounds.createdBy']);
        $latestRound = $freshOrder->rounds->sortByDesc('number')->first();

        return view('print.order-pack', $this->ticketData(
            $freshOrder,
            $unsentItems,
            $voidItems,
            $printItems,
            $isAddition,
            $latestRound,
            $newGeneral,
            $voidGeneral,
        ));
    }

    /**
     * ¿Es una comanda de cambio? Hay algo que no preparar (líneas o salsas generales anuladas
     * después de enviarse) o, en un pedido ya enviado, solo se agregan salsas generales. Una
     * "adición" es únicamente la tradicional: productos nuevos sin nada anulado.
     */
    private static function isChangeTicket(bool $isAddition, Collection $prepare, Collection $voids, Collection $general, Collection $voidGeneral): bool
    {
        return $voids->isNotEmpty()
            || $voidGeneral->isNotEmpty()
            || ($isAddition && $prepare->isEmpty() && $general->isNotEmpty());
    }

    /**
     * Marca de tiempo de una impresión: now(), o el segundo siguiente a la última impresión
     * del pedido (líneas, "NO PREPARAR" y salsas generales) si now() no es posterior.
     */
    private function nextPrintStamp(Order $order): Carbon
    {
        $last = collect()
            ->merge($order->allOrderItems->flatMap(fn ($item): array => [(string) $item->sent_at, (string) $item->void_sent_at]))
            ->merge($order->allGeneralSauces->flatMap(fn ($sauce): array => [(string) $sauce->sent_at, (string) $sauce->void_sent_at]))
            ->filter()
            ->max();

        $stamp = now()->startOfSecond();
        if ($last !== null && $stamp->lte(Carbon::parse($last))) {
            $stamp = Carbon::parse($last)->addSecond();
        }

        return $stamp;
    }

    /**
     * Variables de la comanda, separadas por estación como siempre: cocina y bebidas, cada
     * una con su "✅ PREPARAR" y su "❌ NO PREPARAR".
     */
    private function ticketData(Order $order, Collection $prepare, Collection $voids, Collection $printItems, bool $isAddition, $round, Collection $generalSauces, Collection $voidGeneralSauces, bool $isReprint = false): array
    {
        return [
            'order' => $order,
            'kitchenItems' => $prepare->reject(fn ($item): bool => $this->isBeverageItem($item))->values(),
            'beverageItems' => $prepare->filter(fn ($item): bool => $this->isPreparedByBeverageStation($item))->values(),
            'kitchenVoids' => $voids->reject(fn ($item): bool => $this->isBeverageItem($item))->values(),
            'beverageVoids' => $voids->filter(fn ($item): bool => $this->isPreparedByBeverageStation($item))->values(),
            'voidItems' => $voids,
            'isChange' => self::isChangeTicket($isAddition, $prepare, $voids, $generalSauces, $voidGeneralSauces),
            'takeawayItems' => $printItems,
            'isAddition' => $isAddition,
            'roundNumber' => (int) ($round?->number ?? 1),
            'roundCreatedBy' => $round?->createdBy?->name,
            'isReprint' => $isReprint,
            // Salsas generales de esta impresión (las que se envían ahora) y las anuladas que se
            // anuncian como "❌ NO PREPARAR"; así una adición solo muestra sus salsas nuevas.
            'generalSauces' => $generalSauces,
            'voidGeneralSauces' => $voidGeneralSauces,
        ];
    }

    /**
     * Reimprime la última comanda ya enviada (por ejemplo, si falló la impresora), también
     * si fue una comanda de cambio con "❌ NO PREPARAR". Es de solo lectura: no cambia
     * sent_at, void_sent_at, el estado, las rondas ni el historial.
     */
    public function reprintOrder(Order $order): View
    {
        $order->load([
            'tableSession.restaurantTable',
            'allOrderItems.product.category',
            'allOrderItems.pairedOrderItem.product',
            'allOrderItems.sauces.sauce',
            'allGeneralSauces.sauce',
            'handledBy',
            'rounds.createdBy',
        ]);

        $sentItems = $order->allOrderItems
            ->filter(fn ($item): bool => $item->sent_at !== null)
            ->values();

        if ($sentItems->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => ['Este pedido todavía no tiene comandas enviadas para reimprimir.'],
            ]);
        }

        // sent_at / void_sent_at se guardan como 'Y-m-d H:i:s' (sin cast en el modelo), por lo
        // que la comparación de texto respeta el orden cronológico.
        $lastSentAt = (string) $sentItems
            ->flatMap(fn ($item): array => array_filter([(string) $item->sent_at, (string) $item->void_sent_at]))
            ->merge($order->allGeneralSauces->flatMap(fn ($sauce): array => array_filter([(string) $sauce->sent_at, (string) $sauce->void_sent_at])))
            ->max();
        // Salsas generales de esa tanda: las enviadas (sin las anuladas después) y sus "NO PREPARAR".
        $lastGeneral = $order->allGeneralSauces->filter(fn ($sauce): bool => $sauce->voided_at === null && (string) $sauce->sent_at === $lastSentAt)->values();
        $lastVoidGeneral = $order->allGeneralSauces->filter(fn ($sauce): bool => (string) $sauce->void_sent_at === $lastSentAt)->values();

        // Lo que se envió para preparar en esa tanda (sin las líneas anuladas después) y los
        // "NO PREPARAR" impresos en esa misma tanda.
        $lastBatch = $sentItems
            ->filter(fn ($item): bool => ! $item->isVoided() && (string) $item->sent_at === $lastSentAt)
            ->values();
        $lastVoids = $sentItems
            ->filter(fn ($item): bool => $item->isVoided() && (string) $item->void_sent_at === $lastSentAt)
            ->values();

        $isAddition = $sentItems->contains(
            fn ($item): bool => (string) $item->sent_at < $lastSentAt
        );

        $batchRoundIds = $lastBatch->pluck('order_round_id')->merge($lastVoids->pluck('voided_round_id'))->filter()->unique();
        $latestRound = $order->rounds
            ->whereIn('id', $batchRoundIds)
            ->sortByDesc('number')
            ->first() ?? $order->rounds->sortByDesc('number')->first();

        $activeSent = $sentItems->reject(fn ($item): bool => $item->isVoided())->values();
        $printItems = self::isChangeTicket($isAddition, $lastBatch, $lastVoids, $lastGeneral, $lastVoidGeneral)
            ? $order->allOrderItems->reject(fn ($item): bool => $item->isVoided())->values()
            : ($isAddition ? $lastBatch : $activeSent);

        return view('print.order-pack', $this->ticketData($order, $lastBatch, $lastVoids, $printItems, $isAddition, $latestRound, $lastGeneral, $lastVoidGeneral, true));
    }

    /** Nota con la que queda registrada en el historial la primera impresión de la comanda de cancelación. */
    public const CANCELLATION_TICKET_NOTE = 'Comanda de cancelación impresa.';

    /**
     * "❌ PEDIDO #X CANCELADO — NO PREPARAR" (solo ADMIN): aviso para cocina de un pedido
     * cancelado que ya se había enviado. La primera impresión queda registrada en el historial
     * (sin cambiar el estado); las siguientes salen como reimpresión.
     */
    public function cancellationTicket(Order $order): View
    {
        $isReprint = DB::transaction(function () use ($order): bool {
            // Bloqueo: dos impresiones simultáneas no registran dos veces la primera.
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->cancellationTicketItems($order);

            if ($this->cancellationTicketPrinted($order)) {
                return true;
            }

            $order->statusHistories()->create([
                'previous_status' => OrderStatus::CANCELLED->value,
                'new_status' => OrderStatus::CANCELLED->value,
                'changed_by_user_id' => Auth::id(),
                'changed_at' => now(),
                'notes' => self::CANCELLATION_TICKET_NOTE,
            ]);

            return false;
        });

        return view('print.cancellation', $this->cancellationTicketData($order) + ['isReprint' => $isReprint]);
    }

    /**
     * Reimpresión de la comanda de cancelación (ADMIN y MESERO, como las demás reimpresiones).
     * Solo si caja ya la imprimió una vez; es de solo lectura.
     */
    public function reprintCancellationTicket(Order $order): View
    {
        $this->cancellationTicketItems($order);

        if (! $this->cancellationTicketPrinted($order)) {
            throw ValidationException::withMessages([
                'status' => ['Caja todavía no ha impreso la comanda de cancelación de este pedido.'],
            ]);
        }

        return view('print.cancellation', $this->cancellationTicketData($order) + ['isReprint' => true]);
    }

    /** ¿Caja ya imprimió la comanda de cancelación de este pedido? */
    private function cancellationTicketPrinted(Order $order): bool
    {
        return $order->statusHistories()
            ->where('new_status', OrderStatus::CANCELLED->value)
            ->where('notes', self::CANCELLATION_TICKET_NOTE)
            ->exists();
    }

    /**
     * Lo que cocina recibió y todavía no sabe que no debe preparar: líneas enviadas activas
     * y líneas anuladas sin aviso. Exige un pedido CANCELADO que se haya enviado a cocina.
     */
    private function cancellationTicketItems(Order $order): Collection
    {
        if ($order->status !== OrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Solo los pedidos cancelados tienen comanda de cancelación.'],
            ]);
        }

        $sent = $order->allOrderItems()
            ->with(['product.category', 'sauces.sauce', 'pairedOrderItem.product'])
            ->whereNotNull('sent_at')
            ->get()
            ->filter(fn ($item): bool => ! $item->isVoided() || $item->void_sent_at === null)
            ->values();

        if ($sent->isEmpty()) {
            throw ValidationException::withMessages([
                'status' => ['Este pedido nunca se envió a cocina: no hace falta comanda de cancelación.'],
            ]);
        }

        return $sent;
    }

    private function cancellationTicketData(Order $order): array
    {
        $order->load(['tableSession.restaurantTable', 'handledBy']);
        $sent = $this->cancellationTicketItems($order);
        // La transición real a CANCELADO (no el registro de la impresión de la comanda).
        $cancellation = $order->statusHistories()
            ->where('new_status', OrderStatus::CANCELLED->value)
            ->where('previous_status', '!=', OrderStatus::CANCELLED->value)
            ->latest('id')
            ->first();

        return [
            'order' => $order,
            'kitchenItems' => $sent->reject(fn ($item): bool => $this->isBeverageItem($item))->values(),
            'beverageItems' => $sent->filter(fn ($item): bool => $this->isPreparedByBeverageStation($item))->values(),
            'reason' => (string) str($cancellation?->notes ?? '')->after('Motivo de la cancelación: '),
            'cancelledAt' => $cancellation?->changed_at,
            // Salsas generales que cocina recibió y todavía no sabe que no debe preparar.
            'generalSauces' => $order->allGeneralSauces()->with('sauce')->whereNotNull('sent_at')->whereNull('void_sent_at')->get(),
        ];
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
        // Imprimir la cuenta no cambia el estado: los pedidos ya están POR COBRAR desde que
        // se imprimieron sus comandas.
        $this->loadActiveAccount($tableSession);
        $orders = $tableSession->orders;
        $total = $orders->sum('total');

        return view('print.account', compact('tableSession', 'orders', 'total'));
    }

    private function loadActiveAccount(TableSession $tableSession): void
    {
        $tableSession->load([
            'restaurantTable',
            'orders' => fn ($query) => $query
                ->whereIn('status', OrderStatus::activeValues())
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

            // Los pedidos CANCELADOS (y los TERMINADOS) no forman parte de la cuenta.
            $orders = $session->orders()
                ->lockForUpdate()
                ->whereIn('status', OrderStatus::activeValues())
                ->get();

            if ($orders->isEmpty()) {
                throw ValidationException::withMessages([
                    'table' => ['No hay pedidos pendientes de cobro en esta mesa.'],
                ]);
            }

            // Se cobra desde POR COBRAR (y sus equivalentes heredados); nunca PENDIENTE.
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

        DB::transaction(function () use ($order): void {
            // El estado se comprueba sobre la fila bloqueada: un pedido cancelado mientras
            // tanto nunca termina como TERMINADO.
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            // PARA_LLEVAR y DOMICILIO se cobran desde POR COBRAR (y sus equivalentes
            // heredados), con o sin "🛵 Salió"; nunca PENDIENTE ni CANCELADO.
            if (! $order->status->isCollectable()) {
                throw ValidationException::withMessages([
                    'status' => ['El pedido todavía no está listo para cerrar y registrar el pago.'],
                ]);
            }

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

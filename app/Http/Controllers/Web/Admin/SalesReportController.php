<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RestaurantTable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function daily(): View
    {
        return $this->renderDailyReport();
    }

    public function closeDay(): View
    {
        $this->ensureNoActiveOrders();

        $report = $this->buildDailyReport();

        DB::transaction(function (): void {
            // Se vuelve a comprobar dentro de la transacción para no borrar
            // un pedido creado mientras se generaba el reporte.
            $this->ensureNoActiveOrders(lock: true);

            $orderIds = Order::query()->pluck('id');

            if ($orderIds->isNotEmpty()) {
                DB::table('order_status_histories')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                Order::query()->whereIn('id', $orderIds)->delete();
            }

            DB::table('table_sessions')->delete();
            RestaurantTable::query()->update(['status' => 'AVAILABLE']);
        });

        // MySQL ejecuta ALTER TABLE como una operación que confirma la transacción
        // implícitamente, por eso el reinicio del autoincremento debe hacerse fuera
        // del bloque DB::transaction(). SQLite se maneja de forma equivalente.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DELETE FROM sqlite_sequence WHERE name = 'orders'");
        } else {
            DB::statement('ALTER TABLE orders AUTO_INCREMENT = 1');
        }

        return view('admin.reports.daily', $report + ['closed' => true]);
    }

    /**
     * Impide cerrar el día mientras exista algún pedido sin terminar.
     * Los pedidos CANCELADO (estado heredado) no se consideran activos
     * porque ya no pueden avanzar en el flujo operativo.
     */
    private function ensureNoActiveOrders(bool $lock = false): void
    {
        $query = Order::query()->whereIn('status', [
            OrderStatus::PENDING->value,
            OrderStatus::PREPARING->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::IN_TRANSIT->value,
        ]);

        if ($lock) {
            $query->lockForUpdate();
        }

        $activeOrders = $query->count();

        if ($activeOrders > 0) {
            throw ValidationException::withMessages([
                'close' => [
                    "No se puede cerrar el día: hay {$activeOrders} ".($activeOrders === 1 ? 'pedido que todavía no está TERMINADO' : 'pedidos que todavía no están TERMINADOS').'. Finaliza primero los pedidos pendientes (cobrar mesas, para llevar y domicilios). No se eliminó ningún registro.',
                ],
            ]);
        }
    }

    private function renderDailyReport(): View
    {
        return view('admin.reports.daily', $this->buildDailyReport() + ['closed' => false]);
    }

    private function buildDailyReport(): array
    {
        $day = now()->startOfDay();
        $nextDay = $day->copy()->addDay();
        $completed = OrderStatus::COMPLETED->value;

        $paidOrders = Order::query()
            ->where('status', $completed)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $day)
            ->where('paid_at', '<', $nextDay);

        $summary = [
            'revenue' => (float) $paidOrders->sum('total'),
            'orders' => (clone $paidOrders)->count(),
            'items' => (float) DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', $completed)
                ->whereNotNull('orders.paid_at')
                ->where('orders.paid_at', '>=', $day)
                ->where('orders.paid_at', '<', $nextDay)
                ->sum('order_items.quantity'),
            'average_ticket' => 0,
            'table_orders' => (clone $paidOrders)->where('type', 'MESA')->count(),
            'takeaway_orders' => (clone $paidOrders)->where('type', 'PARA_LLEVAR')->count(),
            'tables_served' => (clone $paidOrders)->where('type', 'MESA')->whereNotNull('table_session_id')->distinct('table_session_id')->count('table_session_id'),
            'unique_waiters' => (clone $paidOrders)->whereNotNull('handled_by_user_id')->distinct('handled_by_user_id')->count('handled_by_user_id'),
        ];
        $summary['average_ticket'] = $summary['orders'] > 0 ? $summary['revenue'] / $summary['orders'] : 0;

        $productSales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('products.name', 'categories.name as category', DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.total) as revenue'))
            ->groupBy('products.id', 'products.name', 'categories.name')
            ->orderByDesc('quantity')->orderByDesc('revenue')->get();

        $categorySales = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('categories.name as category', DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.total) as revenue'))
            ->groupBy('categories.id', 'categories.name')->orderByDesc('revenue')->get();

        $waiterSales = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.handled_by_user_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('users.id', 'users.name', DB::raw('COUNT(orders.id) as orders_count'), DB::raw('SUM(orders.total) as revenue'))
            ->groupBy('users.id', 'users.name')->orderByDesc('orders_count')->orderByDesc('revenue')->get();

        $deliveryStats = DB::table('orders')
            ->join('users', 'users.id', '=', 'orders.delivered_by_user_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('users.name', DB::raw('COUNT(orders.id) as delivered_count'))
            ->groupBy('users.id', 'users.name')->orderByDesc('delivered_count')->get();

        // HOUR() es propio de MySQL/MariaDB; SQLite (usado en las pruebas) necesita strftime().
        $hourExpression = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', paid_at) AS INTEGER)"
            : 'HOUR(paid_at)';

        $hourlySales = (clone $paidOrders)
            ->select(DB::raw("{$hourExpression} as hour"), DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as revenue'))
            ->groupBy(DB::raw($hourExpression))->orderBy('hour')->get();

        $statusCounts = Order::query()
            ->where('created_at', '>=', $day)->where('created_at', '<', $nextDay)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')->orderByDesc('total')->get();

        $paymentTimeline = (clone $paidOrders)
            ->with(['paidBy', 'handledBy', 'deliveredBy', 'tableSession.restaurantTable', 'orderItems.product'])
            ->orderBy('paid_at')->get();

        return compact('summary', 'productSales', 'categorySales', 'waiterSales', 'deliveryStats', 'hourlySales', 'statusCounts', 'paymentTimeline');
    }
}

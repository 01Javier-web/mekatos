<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalesReportController extends Controller
{
    public function daily(): View
    {
        $date = request()->input('date', now()->toDateString());
        try {
            $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            $day = now()->startOfDay();
            $date = $day->toDateString();
        }
        $nextDay = $day->copy()->addDay();
        $completed = OrderStatus::COMPLETED->value;

        $paidOrders = Order::query()->where('status', $completed)->whereNotNull('paid_at')
            ->where('paid_at', '>=', $day)->where('paid_at', '<', $nextDay);

        $summary = [
            'revenue' => (float) $paidOrders->sum('total'),
            'orders' => (clone $paidOrders)->count(),
            'items' => (float) DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', $completed)->whereNotNull('orders.paid_at')
                ->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)->sum('order_items.quantity'),
            'average_ticket' => 0,
            'table_orders' => (clone $paidOrders)->where('type', 'MESA')->count(),
            'takeaway_orders' => (clone $paidOrders)->where('type', 'PARA_LLEVAR')->count(),
            'tables_served' => (clone $paidOrders)->where('type', 'MESA')->whereNotNull('table_session_id')->distinct('table_session_id')->count('table_session_id'),
            'unique_waiters' => (clone $paidOrders)->whereNotNull('handled_by_user_id')->distinct('handled_by_user_id')->count('handled_by_user_id'),
        ];
        $summary['average_ticket'] = $summary['orders'] > 0 ? $summary['revenue'] / $summary['orders'] : 0;

        $productSales = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('products.name', 'categories.name as category', DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.total) as revenue'))
            ->groupBy('products.id', 'products.name', 'categories.name')->orderByDesc('quantity')->orderByDesc('revenue')->get();

        $categorySales = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('categories.name as category', DB::raw('SUM(order_items.quantity) as quantity'), DB::raw('SUM(order_items.total) as revenue'))
            ->groupBy('categories.id', 'categories.name')->orderByDesc('revenue')->get();

        $waiterSales = DB::table('orders')->join('users', 'users.id', '=', 'orders.handled_by_user_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('users.id', 'users.name', DB::raw('COUNT(orders.id) as orders_count'), DB::raw('SUM(orders.total) as revenue'))
            ->groupBy('users.id', 'users.name')->orderByDesc('orders_count')->orderByDesc('revenue')->get();

        $deliveryStats = DB::table('orders')->join('users', 'users.id', '=', 'orders.delivered_by_user_id')
            ->where('orders.status', $completed)->whereNotNull('orders.paid_at')->where('orders.paid_at', '>=', $day)->where('orders.paid_at', '<', $nextDay)
            ->select('users.name', DB::raw('COUNT(orders.id) as delivered_count'))
            ->groupBy('users.id', 'users.name')->orderByDesc('delivered_count')->get();

        $hourlySales = (clone $paidOrders)->select(DB::raw("HOUR(paid_at) as hour"), DB::raw('COUNT(*) as orders_count'), DB::raw('SUM(total) as revenue'))
            ->groupBy(DB::raw('HOUR(paid_at)'))->orderBy('hour')->get();

        $statusCounts = Order::query()->where('created_at', '>=', $day)->where('created_at', '<', $nextDay)
            ->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->orderByDesc('total')->get();

        $paymentTimeline = (clone $paidOrders)->with(['paidBy', 'handledBy', 'deliveredBy', 'tableSession.restaurantTable', 'orderItems.product'])
            ->orderBy('paid_at')->get();

        return view('admin.reports.daily', compact('date', 'summary', 'productSales', 'categorySales', 'waiterSales', 'deliveryStats', 'hourlySales', 'statusCounts', 'paymentTimeline'));
    }
}

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
        return view('waiter.orders', [
            'orders' => Order::query()
                ->with(['tableSession.restaurantTable', 'orderItems.product'])
                ->whereIn('status', [OrderStatus::PENDING, OrderStatus::PREPARING, OrderStatus::READY])
                ->latest()
                ->get(),
        ]);
    }
}

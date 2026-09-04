<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'ordersCount' => Order::count(),
            'pendingOrders' => Order::where('status', OrderStatus::PENDING)->count(),
            'readyOrders' => Order::where('status', OrderStatus::READY)->count(),
            'productsCount' => Product::count(),
            'categoriesCount' => Category::count(),
            'tablesCount' => RestaurantTable::count(),
            'usersCount' => User::count(),
            'recentOrders' => Order::with('tableSession.restaurantTable')->latest()->take(6)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;

class OrderController extends Controller
{
    /**
     * Muestra el listado de pedidos en Blade.
     */
    public function index()
    {
        // Cargamos las relaciones que necesita la vista
        // para evitar consultas adicionales por cada pedido.
        $orders = Order::query()
            ->with([
                'tableSession.restaurantTable',
                'orderItems.product',
            ])
            ->latest()
            ->get();

        // Enviamos la colección $orders a:
        // resources/views/admin/orders/index.blade.php
        return view('admin.orders.index', [
            'orders' => $orders,
        ]);
    }
}
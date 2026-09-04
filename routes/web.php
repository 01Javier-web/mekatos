<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RestaurantTableController;
use App\Http\Controllers\Web\Admin\OrderController;

Route::get('/', function () {
    return redirect()->route('admin.orders.index');
});

Route::get('/admin/orders', [OrderController::class, 'index'])
    ->name('admin.orders.index');

Route::get('/tables', [RestaurantTableController::class, 'index']);

Route::post('/tables', [RestaurantTableController::class, 'store']);
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RestaurantTableController;
use App\Http\Controllers\Web\Admin\OrderController;
use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\ProductController;

Route::get('/', function () {
    return redirect()->route('admin.orders.index');
});

Route::get('/admin/orders', [OrderController::class, 'index'])
    ->name('admin.orders.index');

Route::get('/admin/categories', [CategoryController::class, 'index'])
    ->name('admin.categories.index');
Route::get('/admin/categories/create', [CategoryController::class, 'create'])
    ->name('admin.categories.create');
Route::post('/admin/categories', [CategoryController::class, 'store'])
    ->name('admin.categories.store');
Route::get('/admin/categories/{category}/edit', [CategoryController::class, 'edit'])
    ->name('admin.categories.edit');
Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])
    ->name('admin.categories.update');
Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy'])
    ->name('admin.categories.destroy');

Route::get('/admin/products', [ProductController::class, 'index'])
    ->name('admin.products.index');
Route::get('/admin/products/create', [ProductController::class, 'create'])
    ->name('admin.products.create');
Route::post('/admin/products', [ProductController::class, 'store'])
    ->name('admin.products.store');
Route::get('/admin/products/{product}/edit', [ProductController::class, 'edit'])
    ->name('admin.products.edit');
Route::put('/admin/products/{product}', [ProductController::class, 'update'])
    ->name('admin.products.update');
Route::delete('/admin/products/{product}', [ProductController::class, 'destroy'])
    ->name('admin.products.destroy');

Route::get('/tables', [RestaurantTableController::class, 'index']);

Route::post('/tables', [RestaurantTableController::class, 'store']);

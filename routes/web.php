<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\OrderController;
use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\ProductController;
use App\Http\Controllers\Web\Admin\TableController;
use App\Http\Controllers\Web\Admin\UserController;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])
        ->middleware('role:ADMIN')
        ->name('admin.dashboard');

    Route::get('/admin/orders', [OrderController::class, 'index'])
        ->name('admin.orders.index');
    Route::get('/admin/orders/{order}', [OrderController::class, 'show'])
        ->name('admin.orders.show');
    Route::put('/admin/orders/{order}/status', [OrderController::class, 'updateStatus'])
        ->middleware('role:ADMIN')
        ->name('admin.orders.status');
    Route::put('/admin/orders/{order}/deliver', [OrderController::class, 'deliver'])
        ->middleware('role:ADMIN,MESERO')
        ->name('admin.orders.deliver');

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
        Route::get('/admin/categories/create', [CategoryController::class, 'create'])->name('admin.categories.create');
        Route::post('/admin/categories', [CategoryController::class, 'store'])->name('admin.categories.store');
        Route::get('/admin/categories/{category}/edit', [CategoryController::class, 'edit'])->name('admin.categories.edit');
        Route::put('/admin/categories/{category}', [CategoryController::class, 'update'])->name('admin.categories.update');
        Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.categories.destroy');

        Route::get('/admin/products', [ProductController::class, 'index'])->name('admin.products.index');
        Route::get('/admin/products/create', [ProductController::class, 'create'])->name('admin.products.create');
        Route::post('/admin/products', [ProductController::class, 'store'])->name('admin.products.store');
        Route::get('/admin/products/{product}/edit', [ProductController::class, 'edit'])->name('admin.products.edit');
        Route::put('/admin/products/{product}', [ProductController::class, 'update'])->name('admin.products.update');
        Route::delete('/admin/products/{product}', [ProductController::class, 'destroy'])->name('admin.products.destroy');

        Route::get('/admin/tables', [TableController::class, 'index'])->name('admin.tables.index');
        Route::get('/admin/tables/create', [TableController::class, 'create'])->name('admin.tables.create');
        Route::post('/admin/tables', [TableController::class, 'store'])->name('admin.tables.store');
        Route::get('/admin/tables/{restaurantTable}/edit', [TableController::class, 'edit'])->name('admin.tables.edit');
        Route::put('/admin/tables/{restaurantTable}', [TableController::class, 'update'])->name('admin.tables.update');
        Route::delete('/admin/tables/{restaurantTable}', [TableController::class, 'destroy'])->name('admin.tables.destroy');

        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});

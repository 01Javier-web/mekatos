<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\ClientController;
use App\Http\Controllers\Web\WaiterController;
use App\Http\Controllers\Web\PrintController;
use App\Http\Controllers\Web\Admin\DashboardController;
use App\Http\Controllers\Web\Admin\OrderController;
use App\Http\Controllers\Web\Admin\CategoryController;
use App\Http\Controllers\Web\Admin\ProductController;
use App\Http\Controllers\Web\Admin\TableController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\Admin\SalesReportController;
use App\Http\Controllers\Web\Admin\JuiceFruitController;
use App\Http\Controllers\Web\Admin\BeverageOptionController;

Route::get('/', fn () => redirect()->route('login'));
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/mesa/{token}', [ClientController::class, 'table'])->name('client.table');

Route::middleware('auth')->group(function () {
    Route::get('/waiter/orders', [WaiterController::class, 'index'])->middleware('role:ADMIN,MESERO')->name('waiter.orders');
    Route::get('/admin/orders/create', [OrderController::class, 'create'])->middleware('role:ADMIN,MESERO')->name('admin.orders.create');
    Route::post('/admin/orders', [OrderController::class, 'store'])->middleware('role:ADMIN,MESERO')->name('admin.orders.store');

    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->middleware('role:ADMIN')->name('admin.dashboard');
    Route::get('/admin/reports/daily', [SalesReportController::class, 'daily'])->middleware('role:ADMIN')->name('admin.reports.daily');
    Route::post('/admin/reports/daily/close', [SalesReportController::class, 'closeDay'])->middleware('role:ADMIN')->name('admin.reports.daily.close');
    Route::get('/admin/orders', [OrderController::class, 'index'])->middleware('role:ADMIN')->name('admin.orders.index');
    Route::get('/admin/orders/pending', [OrderController::class, 'pending'])->middleware('role:ADMIN,MESERO')->name('admin.orders.pending');
    Route::get('/admin/orders/{order}', [OrderController::class, 'show'])->middleware('role:ADMIN')->name('admin.orders.show');
    Route::put('/admin/orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('role:ADMIN')->name('admin.orders.status');
    Route::put('/admin/orders/{order}/deliver', [OrderController::class, 'deliver'])->middleware('role:ADMIN,MESERO')->name('admin.orders.deliver');

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin/orders/{order}/print', [PrintController::class, 'orderPack'])->name('admin.orders.print');
    });

    Route::middleware('role:ADMIN,MESERO')->group(function () {
        Route::get('/admin/table-sessions/{tableSession}/account', [PrintController::class, 'account'])->name('admin.accounts.show');
        Route::get('/admin/table-sessions/{tableSession}/account/print', [PrintController::class, 'printAccount'])->name('admin.accounts.print');
        Route::post('/admin/table-sessions/{tableSession}/pay', [PrintController::class, 'payTableSession'])->name('admin.accounts.pay');
        Route::post('/admin/orders/{order}/pay', [PrintController::class, 'payOrder'])->name('admin.orders.pay');
    });

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin/settings', fn () => view('admin.settings.index'))->name('admin.settings');
        Route::get('/admin/settings/products', [ProductController::class, 'settingsIndex'])->name('admin.settings.products.index');
        Route::get('/admin/settings/products/create', [ProductController::class, 'create'])->name('admin.settings.products.create');
        Route::post('/admin/settings/products', [ProductController::class, 'store'])->name('admin.settings.products.store');
        Route::get('/admin/settings/products/{product}/edit', [ProductController::class, 'edit'])->name('admin.settings.products.edit');
        Route::put('/admin/settings/products/{product}', [ProductController::class, 'update'])->name('admin.settings.products.update');
        Route::delete('/admin/settings/products/{product}', [ProductController::class, 'destroy'])->name('admin.settings.products.destroy');

        Route::get('/admin/categories', [CategoryController::class, 'index'])->name('admin.categories.index');
        Route::put('/admin/categories/{category}/availability', [CategoryController::class, 'toggleAvailability'])->name('admin.categories.availability');

        Route::get('/admin/settings/categories', [CategoryController::class, 'settingsIndex'])->name('admin.settings.categories.index');
        Route::get('/admin/settings/categories/create', [CategoryController::class, 'create'])->name('admin.settings.categories.create');
        Route::post('/admin/settings/categories', [CategoryController::class, 'store'])->name('admin.settings.categories.store');
        Route::get('/admin/settings/categories/{category}/edit', [CategoryController::class, 'edit'])->name('admin.settings.categories.edit');
        Route::put('/admin/settings/categories/{category}', [CategoryController::class, 'update'])->name('admin.settings.categories.update');
        Route::delete('/admin/settings/categories/{category}', [CategoryController::class, 'destroy'])->name('admin.settings.categories.destroy');

        Route::get('/admin/products', [ProductController::class, 'index'])->name('admin.products.index');
        Route::put('/admin/products/{product}/availability', [ProductController::class, 'toggleAvailability'])->name('admin.products.availability');

        Route::get('/admin/juice-fruits', [JuiceFruitController::class, 'index'])->name('admin.juice-fruits.index');
        Route::put('/admin/juice-fruits/{juiceFruit}/toggle', [JuiceFruitController::class, 'toggle'])->name('admin.juice-fruits.toggle');
        Route::get('/admin/products/{product}/beverage-options', [BeverageOptionController::class, 'index'])->name('admin.beverage-options.index');
        Route::put('/admin/products/{product}/beverage-options/{beverageOption}/toggle', [BeverageOptionController::class, 'toggle'])->name('admin.beverage-options.toggle');

        Route::get('/admin/tables', [TableController::class, 'index'])->name('admin.tables.index');
        Route::get('/admin/tables/create', [TableController::class, 'create'])->name('admin.tables.create');
        Route::post('/admin/tables', [TableController::class, 'store'])->name('admin.tables.store');
        Route::get('/admin/tables/{restaurantTable}/edit', [TableController::class, 'edit'])->name('admin.tables.edit');
        Route::put('/admin/tables/{restaurantTable}', [TableController::class, 'update'])->name('admin.tables.update');
        Route::post('/admin/tables/{restaurantTable}/release', [TableController::class, 'release'])->name('admin.tables.release');
        Route::delete('/admin/tables/{restaurantTable}', [TableController::class, 'destroy'])->name('admin.tables.destroy');

        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
        Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
        Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    });
});

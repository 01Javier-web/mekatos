<?php

use App\Http\Controllers\TableController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\RestaurantTableController;

// Endpoints públicos utilizados por el menú QR.
Route::get('/table/{token}', [TableController::class, 'show'])->middleware('throttle:60,1');
Route::get('/menu', [MenuController::class, 'index'])->middleware('throttle:60,1');
Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:15,1');
Route::post('/login', [UserController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [UserController::class, 'logout']);

    Route::middleware('role:ADMIN')->group(function () {
        Route::get('/admin/orders', [AdminOrderController::class, 'index']);
        Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show']);
        Route::put('/admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

        Route::get('/admin/users', [AdminUserController::class, 'index']);
        Route::post('/admin/users', [AdminUserController::class, 'store']);
        Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
        Route::put('/admin/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);

        Route::get('/admin/categories', [AdminCategoryController::class, 'index']);
        Route::post('/admin/categories', [AdminCategoryController::class, 'store']);
        Route::get('/admin/categories/{category}', [AdminCategoryController::class, 'show']);
        Route::put('/admin/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/admin/categories/{category}', [AdminCategoryController::class, 'destroy']);

        Route::get('/admin/products', [AdminProductController::class, 'index']);
        Route::post('/admin/products', [AdminProductController::class, 'store']);
        Route::get('/admin/products/{product}', [AdminProductController::class, 'show']);
        Route::put('/admin/products/{product}', [AdminProductController::class, 'update']);
        Route::delete('/admin/products/{product}', [AdminProductController::class, 'destroy']);

        Route::get('/admin/tables', [RestaurantTableController::class, 'index']);
        Route::post('/admin/tables', [RestaurantTableController::class, 'store']);
        Route::get('/admin/tables/{restaurantTable}', [RestaurantTableController::class, 'show']);
        Route::put('/admin/tables/{restaurantTable}', [RestaurantTableController::class, 'update']);
        Route::delete('/admin/tables/{restaurantTable}', [RestaurantTableController::class, 'destroy']);
    });

    Route::put('/orders/{order}/deliver', [OrderController::class, 'deliver'])
        ->middleware('role:ADMIN,MESERO');
});

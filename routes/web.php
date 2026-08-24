<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RestaurantTableController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/tables', [RestaurantTableController::class, 'index']);

Route::post('/tables', [RestaurantTableController::class, 'store']);
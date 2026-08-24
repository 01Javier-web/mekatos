<?php

namespace App\Http\Controllers;

use App\Models\RestaurantTable;
use Illuminate\Http\Request;

class RestaurantTableController extends Controller
{
    public function index()
    {
        $tables = RestaurantTable::all();

        return response()->json($tables);
    }

    public function store(Request $request)
    {
        $table = RestaurantTable::create($request->validate([
            'number' => 'required|integer',
            'name' => 'nullable|string|max:100',
            'capacity' => 'required|integer|min:1',
            'qr_token' => 'required|string|max:255|unique:restaurant_tables,qr_token',
            'status' => 'required|string|max:50',
        ]));

        return response()->json($table, 201);
    }
}
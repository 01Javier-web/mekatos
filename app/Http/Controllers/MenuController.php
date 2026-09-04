<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;

class MenuController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::with([
            'products' => function ($query) {
                $query->where('is_available', true);
            }
        ])->get();

        return response()->json($categories);
    }
}
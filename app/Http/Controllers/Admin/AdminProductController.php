<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Database\QueryException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->with('category')
            ->latest()
            ->get();

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image_path' => ['nullable', 'string', 'max:255'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $product = Product::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'image_path' => $validated['image_path'] ?? null,
            'is_available' => $validated['is_available'] ?? true,
        ]);

        $product->load('category');

        return response()->json([
            'message' => 'Producto creado exitosamente',
            'product' => $product,
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('category');

        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_available' => ['sometimes', 'boolean'],
        ]);

        $product->update($validated);
        $product->load('category');

        return response()->json([
            'message' => 'Producto actualizado exitosamente',
            'product' => $product,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            $product->delete();
        } catch (QueryException $e) {
            // 23000: restricción de integridad (el registro está en uso por otros datos).
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            return response()->json(['message' => 'No se puede eliminar: el producto está registrado en pedidos.'], 409);
        }

        return response()->json([
            'message' => 'Producto eliminado exitosamente',
        ]);
    }
}
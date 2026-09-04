<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    // Listar todos los usuarios
    public function index(): JsonResponse
    {
        $users = User::query()
            ->latest()
            ->get();

        return response()->json($users);
    }

    // Crear un nuevo usuario
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
            ],
            'role' => [
                'required',
                Rule::enum(UserRole::class),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => $user,
        ], 201);
    }

    // Mostrar un usuario específico
    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    // Actualizar un usuario
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => [
                'sometimes',
                'string',
                'min:8',
            ],
            'role' => [
                'sometimes',
                Rule::enum(UserRole::class),
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user,
        ]);
    }

    // Eliminar un usuario
    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }
}
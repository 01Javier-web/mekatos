<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Support\LoginAttempts;

class UserController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Misma regla que el login web: inactivos y bloqueados no reciben token.
        $user = LoginAttempts::attempt($validated['email'], $validated['password']);

        if (! $user) {
            return response()->json(['message' => 'Datos incorrectos, intenta de nuevo'], 401);
        }

        $token = $user->createToken('mekatos_api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Bienvenido ' . $user->name,
            'role' => $user->role->value,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}

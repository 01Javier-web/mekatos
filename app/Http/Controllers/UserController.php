<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Psy\Util\Json;

class UserController extends Controller
{
    public function login(Request $request):JsonResponse
    {
        // Validamos las credenciales recibidas.
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
            ],

            'password' => [
                'required',
                'string',
            ],
        ]);

        // Buscamos al usuario por email usando query
        $user = User::query()
            ->where('email', $validated['email'])
            ->first();
        
        // Validamos tanto la existencia como la contraseña
        //Daremos el mismo mensaje de error para no dar pistas a un atacante.
        if (!$user || !password_verify($validated['password'], $user->password)) {
            return response()->json(['message' => 'Datos incorrectos, intenta de nuevo'], 401);
        }

        // Generamos un token para consumir la api
        $token = $user->createToken('mekatos_api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Bienvenido ' . $user->name,
            'role' => $user->role->value,
        ]);
    }

    // Cerrar la sesión del usuario y revocar el token de acceso
    public function logout(Request $request): JsonResponse
    {
        // Revocamos el token de acceso del usuario autenticado
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }

    
}
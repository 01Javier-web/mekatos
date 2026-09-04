<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\UserRole;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Verificar que haya un usuario autenticado
        if (!$request->user()) {
            return response()->json([
                'message' => 'No autenticado.'
            ], 401);
        }

        // Obtener el rol del usuario
        $userRole = $request->user()->role;

        // Verificar si el rol del usuario está permitido
        foreach ($roles as $role) {
            if ($userRole === UserRole::tryFrom($role)) {
                return $next($request);
            }
        }

        // El usuario está autenticado pero no tiene permiso
        return response()->json([
            'message' => 'No tienes permisos para realizar esta acción.'
        ], 403);
    }
}
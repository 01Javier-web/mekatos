<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impide seguir usando el sistema a un usuario desactivado, aunque ya
 * tuviera una sesión iniciada o un token de la API.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_active) {
            return $next($request);
        }

        $message = 'Tu usuario está desactivado. Comunícate con un administrador.';

        // Sesión web: se cierra para que no pueda seguir navegando.
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}

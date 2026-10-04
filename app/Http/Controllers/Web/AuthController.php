<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\LoginAttempts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(
                Auth::user()->role->value === 'ADMIN' ? 'admin.dashboard' : 'waiter.orders'
            );
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Incluye el bloqueo tras 3 intentos fallidos (ver App\Support\LoginAttempts).
        $user = LoginAttempts::attempt($credentials['email'], $credentials['password']);

        if (! $user) {
            return back()
                ->withErrors(['email' => LoginAttempts::GENERIC_ERROR])
                ->withInput($request->only('email'));
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(
            Auth::user()->role->value === 'ADMIN'
                ? route('admin.dashboard')
                : route('waiter.orders')
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->withoutCookie(config('session.cookie'))
            ->withoutCookie('XSRF-TOKEN');
    }
}

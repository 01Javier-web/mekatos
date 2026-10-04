<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminRecovery;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Enlace de recuperación de emergencia del único ADMIN bloqueado.
 * Desbloquea la cuenta y obliga a definir una contraseña nueva.
 * El token es de un solo uso y vence (ver App\Support\AdminRecovery).
 */
class AdminRecoveryController extends Controller
{
    private const INVALID_LINK = 'El enlace de recuperación no es válido o ya venció.';

    public function show(Request $request, string $token): View
    {
        $email = (string) $request->query('email', '');
        $user = $this->adminFor($email);

        $valid = $user && AdminRecovery::broker()->tokenExists($user, $token);

        return view('auth.admin-recovery', [
            'token' => $token,
            'email' => $email,
            'valid' => $valid,
            'invalidMessage' => self::INVALID_LINK,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [], ['password' => 'contraseña']);

        if (! $this->adminFor($validated['email'])) {
            return back()->withErrors(['token' => self::INVALID_LINK]);
        }

        // El broker comprueba el token (hash + vencimiento) y lo elimina tras usarlo.
        $status = AdminRecovery::broker()->reset(
            [
                'email' => $validated['email'],
                'token' => $validated['token'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'failed_login_attempts' => 0,
                    'locked_at' => null,
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['token' => self::INVALID_LINK]);
        }

        return redirect()->route('login')->with('success', 'Acceso recuperado. Inicia sesión con tu nueva contraseña.');
    }

    private function adminFor(string $email): ?User
    {
        if ($email === '') {
            return null;
        }

        return User::query()
            ->where('email', $email)
            ->where('role', UserRole::Admin->value)
            ->first();
    }
}

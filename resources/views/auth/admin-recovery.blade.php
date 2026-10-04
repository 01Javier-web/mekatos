<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Recuperar acceso | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;background:#fff8df}
        .recovery-card{width:min(100%,420px);padding:28px;border:1px solid #e2d5ae;border-radius:16px;background:#fffdf7}
        .recovery-card h1{margin:0 0 8px;font-size:1.5rem}.recovery-card p{margin:0 0 16px;color:#6f6254}
        .recovery-card label{display:block;margin-bottom:12px;font-weight:700}.recovery-card input{display:block;width:100%;margin-top:6px}
    </style>
</head>
<body>
    <main class="recovery-card">
        <h1>Recuperar acceso de administrador</h1>
        @if (! $valid)
            <div class="alert alert-error" role="alert">{{ $invalidMessage }}</div>
            <p>Solicita un enlace nuevo intentando iniciar sesión con la cuenta bloqueada.</p>
            <a class="button" href="{{ route('login') }}">Volver al inicio de sesión</a>
        @else
            <p>La cuenta se desbloqueará y deberás definir una contraseña nueva. Este enlace solo sirve una vez.</p>
            @if ($errors->any())<div class="alert alert-error" role="alert"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('admin.recovery.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">
                <label>Contraseña nueva (mínimo 8 caracteres)<input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                <label>Repite la contraseña<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                <button class="button button-primary" type="submit">Desbloquear y guardar contraseña</button>
            </form>
        @endif
    </main>
</body>
</html>

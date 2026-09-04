<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-brand">Mekatos</div>
            <p class="login-eyebrow">Administración</p>
            <h1 id="login-title">Iniciar sesión</h1>
            <p class="login-subtitle">Ingresa tus credenciales para acceder al sistema.</p>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">
                    <ul class="error-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="login-form">
                @csrf

                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        autocomplete="email"
                        required
                        autofocus
                        placeholder="correo@ejemplo.com"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        placeholder="Ingresa tu contraseña"
                    >
                </div>

                <button type="submit" class="button button-primary login-button">Ingresar</button>
            </form>
        </section>
    </main>
</body>
</html>

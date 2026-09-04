<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171717">
    <title>Iniciar sesión | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-brand"><span class="brand-mark">M</span><span>Mekatos</span></div>
            <p class="login-eyebrow">Sistema de operación</p>
            <h1 id="login-title">Bienvenido</h1>
            <p class="login-subtitle">Inicia sesión para gestionar pedidos, mesas y el menú.</p>
            @if ($errors->any())<div class="alert alert-error" role="alert"><strong>No pudimos iniciar sesión.</strong><ul class="error-list">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('login.store') }}" class="login-form" id="login-form">
                @csrf
                <div class="form-group"><label for="email">Correo electrónico</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="correo@ejemplo.com"></div>
                <div class="form-group"><label for="password">Contraseña</label><div class="password-field"><input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Ingresa tu contraseña"><button type="button" id="toggle-password" aria-label="Mostrar contraseña">Mostrar</button></div></div>
                <button type="submit" class="button button-primary login-button" id="login-submit">Ingresar</button>
            </form>
        </section>
    </main>
<script>const password=document.getElementById('password'),toggle=document.getElementById('toggle-password'),form=document.getElementById('login-form'),submit=document.getElementById('login-submit');toggle.addEventListener('click',()=>{const visible=password.type==='text';password.type=visible?'password':'text';toggle.textContent=visible?'Mostrar':'Ocultar';toggle.setAttribute('aria-label',visible?'Mostrar contraseña':'Ocultar contraseña')});form.addEventListener('submit',()=>{submit.disabled=true;submit.textContent='Ingresando...'})</script>
<style>.password-field{position:relative}.password-field input{padding-right:78px!important}.password-field button{position:absolute;right:7px;top:7px;height:34px;padding:0 9px;border:0;border-radius:7px;background:#f2f2f0;color:#444;font-size:.78rem;font-weight:750}.password-field button:hover{background:#e7e7e4}</style>
</body>
</html>

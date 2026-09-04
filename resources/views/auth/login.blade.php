<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171717">
    <title>Iniciar sesión | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .login-page{position:relative;overflow:hidden}.login-page::before{content:"";position:fixed;width:520px;height:520px;right:-230px;top:-250px;border-radius:50%;background:#171717;opacity:.035;pointer-events:none}.login-page::after{content:"";position:fixed;width:360px;height:360px;left:-190px;bottom:-190px;border-radius:50%;background:#171717;opacity:.04;pointer-events:none}
        .login-card{position:relative}.login-card-header{text-align:left}.login-status{display:flex;align-items:center;gap:7px;margin:0 0 18px;color:#707070;font-size:.76rem}.login-status::before{content:"";width:7px;height:7px;border-radius:50%;background:#2e8b57;box-shadow:0 0 0 4px #edf7f0}.login-help{margin:16px 0 0;text-align:center;color:#8a8a8a;font-size:.74rem}.error-list{margin-bottom:0}.login-form input:focus{border-color:#777;box-shadow:0 0 0 3px rgba(23,23,23,.07)}
        @media(max-width:480px){.login-page{padding:16px}.login-card{padding:27px 21px;border-radius:16px}.login-card h1{font-size:1.75rem}}
    </style>
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-card-header">
                <div class="login-brand"><span class="brand-mark">M</span><span>Mekatos</span></div>
                <p class="login-status">Sistema disponible</p>
                <p class="login-eyebrow">Sistema de operación</p>
                <h1 id="login-title">Bienvenido</h1>
                <p class="login-subtitle">Accede para gestionar pedidos, mesas y el menú de Mekatos.</p>
            </div>
            @if ($errors->any())<div class="alert alert-error" role="alert"><strong>No pudimos iniciar sesión.</strong><ul class="error-list">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route('login.store') }}" class="login-form" id="login-form">
                @csrf
                <div class="form-group"><label for="email">Correo electrónico</label><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus placeholder="correo@ejemplo.com" inputmode="email"></div>
                <div class="form-group"><label for="password">Contraseña</label><div class="password-field"><input id="password" type="password" name="password" autocomplete="current-password" required placeholder="Ingresa tu contraseña"><button type="button" id="toggle-password" aria-label="Mostrar contraseña" aria-pressed="false">Mostrar</button></div></div>
                <button type="submit" class="button button-primary login-button" id="login-submit">Ingresar</button>
            </form>
            <p class="login-help">Usa tus credenciales de acceso asignadas.</p>
        </section>
    </main>
<script>
const password=document.getElementById('password'),toggle=document.getElementById('toggle-password'),form=document.getElementById('login-form'),submit=document.getElementById('login-submit');
toggle.addEventListener('click',()=>{const visible=password.type==='text';password.type=visible?'password':'text';toggle.textContent=visible?'Mostrar':'Ocultar';toggle.setAttribute('aria-label',visible?'Mostrar contraseña':'Ocultar contraseña');toggle.setAttribute('aria-pressed',String(!visible));password.focus()});
form.addEventListener('submit',()=>{submit.disabled=true;submit.textContent='Ingresando...'});
</script>
</body>
</html>

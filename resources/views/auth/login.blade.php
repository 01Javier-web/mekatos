<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#BB2528">
    <title>Iniciar sesión | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        :root{--mk-red:#BB2528;--mk-red-dark:#951d20;--mk-mustard:#E3B83C;--mk-mustard-light:#f6d96c;--mk-cream:#fff8df;--mk-card:#fffdf7;--mk-ink:#2b2118;--mk-muted:#6f6254;--mk-border:#e2d5ae}
        .login-page{position:relative;min-height:100vh;overflow:hidden;display:flex;align-items:center;justify-content:center;padding:28px 18px;background:radial-gradient(circle at 15% 15%,rgba(246,217,108,.55) 0,rgba(246,217,108,0) 30%),radial-gradient(circle at 88% 82%,rgba(227,184,60,.45) 0,rgba(227,184,60,0) 34%),linear-gradient(135deg,#fffdf4 0%,#f8e7ae 48%,#E3B83C 100%)}
        .login-page::before{content:"";position:fixed;width:520px;height:520px;right:-210px;top:-250px;border-radius:50%;background:var(--mk-red);opacity:.08;pointer-events:none}
        .login-page::after{content:"";position:fixed;width:430px;height:430px;left:-230px;bottom:-230px;border-radius:50%;background:var(--mk-red);opacity:.07;pointer-events:none}
        .login-shell{position:relative;z-index:1;width:100%;max-width:450px}
        .login-card{position:relative;background:rgba(255,253,247,.97);border:1px solid var(--mk-border);border-radius:20px;padding:38px;box-shadow:0 24px 70px rgba(83,58,15,.22);overflow:hidden}
        .login-card::before{content:"";position:absolute;left:0;top:0;right:0;height:6px;background:linear-gradient(90deg,var(--mk-red),var(--mk-mustard))}
        .login-card-header{text-align:left}
        .login-brand{display:block;width:100%;height:78px;margin:0 0 25px;color:transparent;font-size:0;overflow:hidden;background-image:url('{{ asset('images/mekatos-logo.png') }}');background-repeat:no-repeat;background-position:center;background-size:cover}
        .login-status{display:inline-flex;align-items:center;gap:8px;margin:0 0 17px;padding:6px 10px;border-radius:999px;background:#edf7e8;color:#315d24;font-size:.72rem;font-weight:800}
        .login-status::before{content:"";width:7px;height:7px;border-radius:50%;background:#3d8b3d;box-shadow:0 0 0 3px #dff0d9}
        .login-eyebrow{margin:0 0 7px;color:var(--mk-red);font-size:.7rem;font-weight:850;letter-spacing:.12em;text-transform:uppercase}
        .login-card h1{margin:0 0 8px;color:var(--mk-ink);font-size:2.2rem;letter-spacing:-.05em}
        .login-subtitle{margin:0 0 28px;color:var(--mk-muted);line-height:1.55}
        .login-form .form-group{margin-bottom:19px}.login-form label{display:block;margin-bottom:7px;color:var(--mk-ink);font-weight:800}
        .login-form input{width:100%;min-height:49px;padding:11px 13px;border:1px solid var(--mk-border);border-radius:10px;background:#fffdf7;color:var(--mk-ink);transition:border-color .15s ease,box-shadow .15s ease,background .15s ease}
        .login-form input::placeholder{color:#9a8d79}.login-form input:focus{border-color:var(--mk-red);background:#fff;box-shadow:0 0 0 4px rgba(187,37,40,.10);outline:none}
        .password-field{position:relative}.password-field input{padding-right:82px}
        #toggle-password{position:absolute;right:7px;top:50%;transform:translateY(-50%);min-height:34px;padding:6px 9px;border:0;border-radius:7px;background:#f8e7ae;color:var(--mk-red-dark);font-size:.75rem;font-weight:800}
        #toggle-password:hover{background:var(--mk-mustard-light)}
        .login-button{width:100%;min-height:50px;margin-top:4px;border-radius:10px;background:var(--mk-red);border-color:var(--mk-red);color:#fff;font-size:.98rem;box-shadow:0 6px 15px rgba(149,29,32,.22)}
        .login-button:hover{background:var(--mk-red-dark);border-color:var(--mk-red-dark);transform:translateY(-1px)}.login-button:disabled{transform:none}
        .login-help{margin:18px 0 0;padding-top:16px;border-top:1px solid #eadfbf;text-align:center;color:#887961;font-size:.74rem}
        .error-list{margin-bottom:0}.alert-error{background:#fff0ef;border-color:#e3b0ad;color:#8f2527}
        @media(max-width:480px){.login-page{padding:16px}.login-card{padding:30px 22px;border-radius:17px}.login-card h1{font-size:1.9rem}.login-brand{height:70px;margin-bottom:20px}}
    </style>
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-card" aria-labelledby="login-title">
            <div class="login-card-header">
                <div class="login-brand" role="img" aria-label="Mekatos Comidas Rápidas"></div>
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

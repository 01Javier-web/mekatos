<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171717">
    <title>Página no encontrada | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="error-page">
    <div class="error-card">
        <span class="eyebrow">Mekatos</span>
        <strong class="error-code">404</strong>
        <h1>Esta página no existe</h1>
        <p>El enlace puede estar incompleto, haber cambiado o ya no estar disponible.</p>
        <a class="button button-primary" href="{{ url('/') }}">Volver al inicio</a>
    </div>
</main>
<style>
.error-page{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f7f7f5}.error-card{width:min(100%,520px);padding:44px 36px;text-align:center;background:#fff;border:1px solid #e5e5e2;border-radius:20px;box-shadow:0 18px 50px rgba(0,0,0,.07)}.error-code{display:block;margin:12px 0 4px;font-size:clamp(4rem,15vw,7rem);line-height:.95;letter-spacing:-.06em}.error-card h1{margin:12px 0 8px}.error-card p{margin:0 auto 24px;max-width:390px;color:#777}.error-card .button{display:inline-flex}@media(max-width:480px){.error-card{padding:34px 22px}}
</style>

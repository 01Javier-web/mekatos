@extends('layouts.app')
@section('title', 'Configuración | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración</span>
            <h2>Configuración</h2>
            <p>Gestiona la configuración del sistema y del catálogo de Mekatos.</p>
        </div>
    </div>

    <section class="settings-grid" aria-label="Opciones de configuración">
        <article class="settings-card">
            <div class="settings-icon" aria-hidden="true">🍔</div>
            <div class="settings-card-content">
                <span class="settings-label">Catálogo</span>
                <h3>Productos</h3>
                <p>Crea nuevos productos, modifica precios y descripciones, cambia su disponibilidad o elimina productos del catálogo.</p>
                <a class="button button-primary" href="{{ route('admin.products.index') }}">Gestionar productos</a>
            </div>
        </article>
    </section>
</div>

<style>
.settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,420px));gap:18px}
.settings-card{display:flex;gap:18px;align-items:flex-start;background:var(--mk-card);border:1px solid var(--mk-border);border-radius:14px;padding:24px;box-shadow:0 9px 28px rgba(83,58,15,.10)}
.settings-icon{width:52px;height:52px;display:grid;place-items:center;flex:0 0 52px;border-radius:12px;background:#f8e7ae;font-size:1.55rem}
.settings-card-content{min-width:0}.settings-label{display:block;margin-bottom:4px;color:var(--mk-muted);font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
.settings-card h3{margin:0 0 8px;color:var(--mk-ink);font-size:1.35rem}.settings-card p{margin:0 0 18px;color:var(--mk-muted);line-height:1.5}.settings-card .button{display:inline-flex}
@media(max-width:560px){.settings-card{padding:20px;gap:14px}.settings-icon{width:46px;height:46px;flex-basis:46px}}
</style>
@endsection

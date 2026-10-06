@extends('layouts.app')
@section('title', 'Salsas | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Menú</span>
            <h2>Salsas</h2>
            <p>Activa o desactiva cada salsa para los pedidos PARA LLEVAR y DOMICILIO.</p>
        </div>
        <a class="button" href="{{ route('admin.settings') }}">← Configuración</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div><h3>Lista de salsas</h3><span>Las salsas desactivadas no se pueden elegir en pedidos nuevos, pero se conservan en los pedidos que ya las usaron.</span></div>
        </div>
        <div class="juice-fruit-list">
            @foreach ($sauces as $sauce)
                <div class="juice-fruit-row">
                    <div>
                        <strong>{{ $sauce->name }}</strong>
                        <small class="{{ $sauce->is_active ? 'is-available' : 'is-unavailable' }}">
                            {{ $sauce->is_active ? 'Activa' : 'Desactivada' }}
                        </small>
                    </div>
                    <form method="POST" action="{{ route('admin.sauces.toggle', $sauce) }}">
                        @csrf
                        @method('PUT')
                        <button class="button {{ $sauce->is_active ? 'button-danger' : 'button-primary' }}" type="submit">
                            {{ $sauce->is_active ? 'Desactivar' : 'Activar' }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
</div>
<style>
.juice-fruit-list{padding:8px 22px 20px}.juice-fruit-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 0;border-bottom:1px solid #eadfbf}.juice-fruit-row:last-child{border-bottom:0}.juice-fruit-row strong{display:block;color:var(--mk-ink)}.juice-fruit-row small{display:block;margin-top:3px;font-size:.74rem;font-weight:750}.is-available{color:#315d24}.is-unavailable{color:var(--mk-red-dark)}
</style>
@endsection

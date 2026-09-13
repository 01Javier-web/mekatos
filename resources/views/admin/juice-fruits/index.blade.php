@extends('layouts.app')
@section('title', 'Frutas para jugos | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Menú</span>
            <h2>Frutas para jugos</h2>
            <p>Activa o desactiva cada fruta según la disponibilidad del restaurante.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div><h3>Jugo Natural Jarra</h3><span>Las frutas agotadas no podrán seleccionarse para nuevos pedidos.</span></div>
        </div>
        <div class="juice-fruit-list">
            @foreach ($fruits as $fruit)
                <div class="juice-fruit-row">
                    <div>
                        <strong>{{ $fruit->name }}</strong>
                        <small class="{{ $fruit->is_available ? 'is-available' : 'is-unavailable' }}">
                            {{ $fruit->is_available ? 'Disponible' : 'Agotada' }}
                        </small>
                    </div>
                    <form method="POST" action="{{ route('admin.juice-fruits.toggle', $fruit) }}">
                        @csrf
                        @method('PUT')
                        <button class="button {{ $fruit->is_available ? 'button-danger' : 'button-primary' }}" type="submit">
                            {{ $fruit->is_available ? 'Marcar agotada' : 'Marcar disponible' }}
                        </button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
</div>
<style>
.juice-fruit-list{padding:8px 22px 20px}.juice-fruit-row{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 0;border-bottom:1px solid #eadfbf}.juice-fruit-row:last-child{border-bottom:0}.juice-fruit-row strong{display:block;color:var(--mk-ink)}.juice-fruit-row small{display:block;margin-top:3px;font-size:.74rem;font-weight:750}.is-available{color:#315d24}.is-unavailable{color:var(--mk-red-dark)}@media(max-width:600px){.juice-fruit-row{align-items:flex-start;flex-direction:column}.juice-fruit-row form,.juice-fruit-row .button{width:100%}}
</style>
@endsection

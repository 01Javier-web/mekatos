@extends('layouts.app')
@section('title', 'Opciones de bebida | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading">
        <div><span class="eyebrow">Administración</span><h2>Opciones de {{ $product->name }}</h2><p>Activa o desactiva cada opción cuando se agote. Los cambios aplican al menú QR.</p></div>
        <a class="button" href="{{ route('admin.products.edit', $product) }}">← Volver al producto</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif

    <div class="form-card options-card">
        @foreach ($options as $option)
            <div class="option-row">
                <div><strong>{{ $option->name }}</strong><span>{{ $option->is_available ? 'Disponible para nuevos pedidos' : 'Agotado / oculto del QR' }}</span></div>
                <form method="POST" action="{{ route('admin.beverage-options.toggle', [$product, $option]) }}">
                    @csrf @method('PUT')
                    <button class="button {{ $option->is_available ? '' : 'button-primary' }}" type="submit">{{ $option->is_available ? 'Marcar agotado' : 'Activar' }}</button>
                </form>
            </div>
        @endforeach
    </div>
</div>
<style>.options-card{padding:0;overflow:hidden}.option-row{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:18px 20px;border-bottom:1px solid #eee}.option-row:last-child{border-bottom:0}.option-row strong{display:block}.option-row span{display:block;margin-top:4px;color:#777;font-size:.76rem}.option-row form{margin:0}@media(max-width:600px){.option-row{align-items:flex-start;flex-direction:column}.option-row form,.option-row .button{width:100%}}</style>
@endsection

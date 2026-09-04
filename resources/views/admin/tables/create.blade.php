@extends('layouts.app')
@section('title', 'Nueva mesa | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Nueva mesa</h2><p>Registra una mesa y genera automáticamente su token QR.</p></div><a class="button" href="{{ route('admin.tables.index') }}">Volver</a></div>
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="form-card" method="POST" action="{{ route('admin.tables.store') }}">
        @csrf
        <div class="form-grid">
            <label><span>Número</span><input type="number" name="number" value="{{ old('number') }}" min="1" required></label>
            <label><span>Nombre</span><input type="text" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Ej. Terraza"></label>
        </div>
        <div class="form-grid">
            <label><span>Capacidad</span><input type="number" name="capacity" value="{{ old('capacity') }}" min="1" required></label>
            <label><span>Estado</span><select name="status"><option value="AVAILABLE">Disponible</option><option value="OCCUPIED">Ocupada</option><option value="CLEANING">Limpieza</option></select></label>
        </div>
        <div class="form-actions"><a class="button" href="{{ route('admin.tables.index') }}">Cancelar</a><button class="button button-primary" type="submit">Crear mesa</button></div>
    </form>
</div>
@endsection

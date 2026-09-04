@extends('layouts.app')
@section('title', 'Editar mesa | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Editar mesa #{{ $table->number }}</h2><p>Actualiza la información de la mesa.</p></div><a class="button" href="{{ route('admin.tables.index') }}">Volver</a></div>
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="form-card" method="POST" action="{{ route('admin.tables.update', $table) }}">
        @csrf @method('PUT')
        <div class="form-grid">
            <label><span>Número</span><input type="number" name="number" value="{{ old('number', $table->number) }}" min="1" required></label>
            <label><span>Nombre</span><input type="text" name="name" value="{{ old('name', $table->name) }}" maxlength="100"></label>
        </div>
        <div class="form-grid">
            <label><span>Capacidad</span><input type="number" name="capacity" value="{{ old('capacity', $table->capacity) }}" min="1" required></label>
            <label><span>Estado</span><select name="status"><option value="AVAILABLE" {{ old('status', $table->status->value) === 'AVAILABLE' ? 'selected' : '' }}>Disponible</option><option value="OCCUPIED" {{ old('status', $table->status->value) === 'OCCUPIED' ? 'selected' : '' }}>Ocupada</option><option value="CLEANING" {{ old('status', $table->status->value) === 'CLEANING' ? 'selected' : '' }}>Limpieza</option></select></label>
        </div>
        <div class="info-box"><strong>Token QR:</strong> {{ $table->qr_token }}<br><a href="{{ url('/mesa/'.$table->qr_token) }}" target="_blank" rel="noopener">Abrir acceso de esta mesa</a></div>
        <div class="form-actions"><a class="button" href="{{ route('admin.tables.index') }}">Cancelar</a><button class="button button-primary" type="submit">Guardar cambios</button></div>
    </form>
</div>
@endsection

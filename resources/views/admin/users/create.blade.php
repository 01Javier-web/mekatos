@extends('layouts.app')
@section('title', 'Nuevo usuario | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Nuevo usuario</h2><p>Crea una cuenta para el personal del restaurante.</p></div><a class="button" href="{{ route('admin.users.index') }}">Volver</a></div>
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="form-card" method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="form-grid">
            <label><span>Nombre</span><input type="text" name="name" value="{{ old('name') }}" required maxlength="255"></label>
            <label><span>Correo electrónico</span><input type="email" name="email" value="{{ old('email') }}" required maxlength="255"></label>
        </div>
        <div class="form-grid">
            <label><span>Contraseña</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
            <label><span>Rol</span><select name="role" required><option value="">Selecciona un rol</option><option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>Administrador</option><option value="MESERO" {{ old('role') === 'MESERO' ? 'selected' : '' }}>Mesero</option></select></label>
        </div>
        <label class="checkbox-label"><input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}><span>Usuario activo</span></label>
        <div class="form-actions"><a class="button" href="{{ route('admin.users.index') }}">Cancelar</a><button class="button button-primary" type="submit">Crear usuario</button></div>
    </form>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Editar usuario | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Editar usuario</h2><p>Actualiza la cuenta de {{ $user->name }}.</p></div><a class="button" href="{{ route('admin.users.index') }}">Volver</a></div>
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="form-card" method="POST" action="{{ route('admin.users.update', $user) }}">
        @csrf @method('PUT')
        <div class="form-grid">
            <label><span>Nombre</span><input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255"></label>
            <label><span>Correo electrónico</span><input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255"></label>
        </div>
        <div class="form-grid">
            <label><span>Nueva contraseña</span><input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Deja vacío para conservarla"></label>
            <label><span>Rol</span><select name="role" required><option value="ADMIN" {{ old('role', $user->role->value) === 'ADMIN' ? 'selected' : '' }}>Administrador</option><option value="MESERO" {{ old('role', $user->role->value) === 'MESERO' ? 'selected' : '' }}>Mesero</option></select></label>
        </div>
        <label class="checkbox-label"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}><span>Usuario activo</span></label>
        <div class="form-actions"><a class="button" href="{{ route('admin.users.index') }}">Cancelar</a><button class="button button-primary" type="submit">Guardar cambios</button></div>
    </form>
</div>
@endsection

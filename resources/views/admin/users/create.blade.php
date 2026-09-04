@extends('layouts.app')
@section('title', 'Nuevo usuario | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Nuevo usuario</h2><p>Crea una cuenta para un integrante del equipo.</p></div><a class="button" href="{{ route('admin.users.index') }}">Volver</a></div>
    @if ($errors->any())<div class="alert alert-error"><strong>Revisa los datos.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="form-card" method="POST" action="{{ route('admin.users.store') }}">
        @csrf
        <div class="form-grid">
            <label><span>Nombre completo</span><input type="text" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" placeholder="Ej. Juan Pérez"></label>
            <label><span>Correo electrónico</span><input type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="usuario@mekatos.test"></label>
        </div>
        <div class="form-grid">
            <label><span>Contraseña</span><div class="password-field"><input id="user-password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres"><button type="button" class="password-toggle" data-target="user-password" aria-label="Mostrar contraseña">Mostrar</button></div></label>
            <label><span>Rol</span><select name="role" id="user-role" required><option value="">Selecciona un rol</option><option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>Administrador</option><option value="MESERO" {{ old('role') === 'MESERO' ? 'selected' : '' }}>Mesero</option></select><small id="role-help" class="form-help">Elige el nivel de acceso que tendrá esta cuenta.</small></label>
        </div>
        <label class="checkbox-label"><input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}><span><strong>Usuario activo</strong><small>Podrá iniciar sesión y operar el sistema.</small></span></label>
        <div class="form-actions"><a class="button" href="{{ route('admin.users.index') }}">Cancelar</a><button class="button button-primary" type="submit">Crear usuario</button></div>
    </form>
</div>
<style>.password-field{position:relative;display:flex}.password-field input{width:100%;padding-right:78px}.password-toggle{position:absolute;right:7px;top:50%;transform:translateY(-50%);border:0;background:transparent;font-weight:700;cursor:pointer;color:#555}.form-help{display:block;margin-top:6px;color:#777}.checkbox-label small{display:block;color:#777;font-weight:400;margin-top:2px}</style>
<script>document.querySelectorAll('.password-toggle').forEach(b=>b.addEventListener('click',()=>{const i=document.getElementById(b.dataset.target);const show=i.type==='password';i.type=show?'text':'password';b.textContent=show?'Ocultar':'Mostrar';b.setAttribute('aria-label',show?'Ocultar contraseña':'Mostrar contraseña')}));</script>
@endsection

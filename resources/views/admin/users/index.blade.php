@extends('layouts.app')

@section('title', 'Usuarios | Mekatos')

@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div><span class="eyebrow">Administración</span><h2>Usuarios</h2><p>Gestiona las cuentas que pueden acceder al sistema.</p></div>
        <a class="button button-primary" href="{{ route('admin.users.create') }}">+ Nuevo usuario</a>
    </div>
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if ($errors->any())
        <div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <section class="panel">
        <div class="panel-header"><h3>Listado de usuarios</h3><span>{{ $users->count() }} registrados</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th class="actions-cell">Acciones</th></tr></thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong></td>
                        <td>{{ $user->email }}</td>
                        <td><span class="status status-role">{{ $user->role->value }}</span></td>
                        <td><span class="status {{ $user->is_active ? 'status-active' : 'status-inactive' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="actions-cell">
                            <a class="button button-small" href="{{ route('admin.users.edit', $user) }}">Editar</a>
                            @if ($user->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline-form" onsubmit="return confirm('¿Eliminar este usuario?')">
                                    @csrf @method('DELETE')
                                    <button class="button button-small button-danger" type="submit">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">Todavía no hay usuarios registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

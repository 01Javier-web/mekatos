@extends('layouts.app')
@section('title', 'Usuarios | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Usuarios</h2><p>Gestiona las cuentas que pueden acceder al sistema.</p></div><a class="button button-primary" href="{{ route('admin.users.create') }}">+ Nuevo usuario</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header"><div><h3>Usuarios registrados</h3><span>{{ $users->count() }} cuentas</span></div><div class="filter-form"><input id="user-search" class="admin-search" type="search" placeholder="Buscar nombre o correo..." aria-label="Buscar usuario"><select id="user-filter" aria-label="Filtrar usuarios"><option value="">Todos</option><option value="ADMIN">Administradores</option><option value="MESERO">Meseros</option><option value="inactive">Inactivos</option></select></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th class="actions-cell">Acciones</th></tr></thead><tbody id="user-rows">
            @forelse ($users as $user)
                <tr data-search="{{ strtolower($user->name.' '.$user->email) }}" data-role="{{ $user->role->value }}" data-status="{{ $user->is_active?'active':'inactive' }}"><td><strong>{{ $user->name }}</strong></td><td>{{ $user->email }}</td><td><span class="status status-role">{{ $user->role->value === 'ADMIN' ? 'Administrador' : 'Mesero' }}</span></td><td><span class="status {{ $user->is_active?'status-active':'status-inactive' }}">{{ $user->is_active?'Activo':'Inactivo' }}</span></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.users.edit',$user) }}">Editar</a>@if($user->id!==auth()->id())<form method="POST" action="{{ route('admin.users.destroy',$user) }}" class="inline-form" onsubmit="return confirm('¿Eliminar este usuario? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form>@endif</td></tr>
            @empty<tr><td colspan="5" class="empty-state"><h3>No hay usuarios</h3><p>Crea la primera cuenta de operación.</p><a class="button button-primary" href="{{ route('admin.users.create') }}">Nuevo usuario</a></td></tr>@endforelse
        </tbody></table></div>
        <div id="users-empty" class="empty-state" hidden><h3>Sin resultados</h3><p>No encontramos usuarios con esos filtros.</p></div>
    </section>
</div>
<style>.admin-search{min-height:36px;width:220px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.data-table tr[hidden]{display:none}@media(max-width:760px){.filter-form{flex-wrap:wrap}.admin-search{width:100%}}</style>
<script>const us=document.getElementById('user-search'),uf=document.getElementById('user-filter'),ue=document.getElementById('users-empty');function filterUsers(){const q=us.value.trim().toLowerCase(),f=uf.value;let shown=0;document.querySelectorAll('#user-rows tr[data-search]').forEach(r=>{const role=f==='ADMIN'||f==='MESERO',ok=(!q||r.dataset.search.includes(q))&&(!f||(role?r.dataset.role===f:r.dataset.status===f));r.hidden=!ok;if(ok)shown++});ue.hidden=shown!==0||document.querySelectorAll('#user-rows tr[data-search]').length===0}us?.addEventListener('input',filterUsers);uf?.addEventListener('change',filterUsers);</script>
@endsection

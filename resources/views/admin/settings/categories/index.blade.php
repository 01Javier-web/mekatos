@extends('layouts.app')
@section('title', 'Configuración · Categorías | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div><span class="eyebrow">Configuración</span><h2>Gestión de categorías</h2><p>Aquí puedes crear, editar o eliminar categorías del menú.</p></div>
        <div class="page-heading-actions"><a class="button" href="{{ route('admin.settings') }}">← Configuración</a><a class="button button-primary" href="{{ route('admin.settings.categories.create') }}">+ Nueva categoría</a></div>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header"><div><h3>Categorías registradas</h3><span>{{ $categories->count() }} categorías</span></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Categoría</th><th>Descripción</th><th>Estado</th><th class="actions-cell">Acciones</th></tr></thead><tbody>
        @forelse ($categories as $category)
            <tr><td><strong>{{ $category->name }}</strong><small>Orden: {{ $category->sort_order }}</small></td><td>{{ $category->description ?: 'Sin descripción' }}</td><td><span class="status {{ $category->is_active?'status-active':'status-inactive' }}">{{ $category->is_active?'Habilitada':'Deshabilitada' }}</span></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.settings.categories.edit',$category) }}">Editar</a><form method="POST" action="{{ route('admin.settings.categories.destroy',$category) }}" class="inline-form" onsubmit="return confirm('¿Eliminar esta categoría? Esta acción puede afectar sus productos.')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form></td></tr>
        @empty
            <tr><td colspan="4" class="empty-state"><h3>No hay categorías</h3><p>Crea la primera categoría del menú.</p><a class="button button-primary" href="{{ route('admin.settings.categories.create') }}">Nueva categoría</a></td></tr>
        @endforelse
        </tbody></table></div>
    </section>
</div>
<style>.page-heading-actions{display:flex;gap:8px;align-items:center}.actions-cell{white-space:nowrap}.inline-form{display:inline-block;margin-left:5px}@media(max-width:650px){.page-heading-actions{width:100%;flex-wrap:wrap}.page-heading-actions .button{flex:1}.actions-cell{white-space:normal}.inline-form{margin:5px 0 0}}</style>
@endsection
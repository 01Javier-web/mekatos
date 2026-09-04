@extends('layouts.app')
@section('title', 'Categorías | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Categorías</h2><p>Organiza el menú y controla qué categorías están visibles.</p></div><a class="button button-primary" href="{{ route('admin.categories.create') }}">+ Nueva categoría</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header"><div><h3>Listado de categorías</h3><span>{{ $categories->count() }} registradas</span></div><input id="category-search" class="admin-search" type="search" placeholder="Buscar categoría..." aria-label="Buscar categoría"></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Orden</th><th>Nombre</th><th>Descripción</th><th>Estado</th><th class="actions-cell">Acciones</th></tr></thead><tbody id="category-rows">
            @forelse ($categories as $category)
                <tr data-search="{{ strtolower($category->name.' '.($category->description??'')) }}"><td>{{ $category->sort_order }}</td><td><strong>{{ $category->name }}</strong></td><td>{{ $category->description ?: 'Sin descripción' }}</td><td><span class="status {{ $category->is_active?'status-active':'status-inactive' }}">{{ $category->is_active?'Activa':'Inactiva' }}</span></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.categories.edit',$category) }}">Editar</a><form method="POST" action="{{ route('admin.categories.destroy',$category) }}" class="inline-form" onsubmit="return confirm('¿Eliminar esta categoría? Esta acción puede afectar sus productos.')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form></td></tr>
            @empty<tr><td colspan="5" class="empty-state"><h3>No hay categorías</h3><p>Crea la primera categoría del menú.</p><a class="button button-primary" href="{{ route('admin.categories.create') }}">Nueva categoría</a></td></tr>@endforelse
        </tbody></table></div>
        <div id="categories-empty" class="empty-state" hidden><h3>Sin resultados</h3><p>No encontramos categorías con esa búsqueda.</p></div>
    </section>
</div>
<style>.admin-search{min-height:36px;width:220px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.data-table tr[hidden]{display:none}@media(max-width:600px){.admin-search{width:100%}}</style>
<script>const cs=document.getElementById('category-search'),ce=document.getElementById('categories-empty');cs?.addEventListener('input',()=>{const q=cs.value.trim().toLowerCase();let shown=0;document.querySelectorAll('#category-rows tr[data-search]').forEach(r=>{const ok=!q||r.dataset.search.includes(q);r.hidden=!ok;if(ok)shown++});ce.hidden=shown!==0||document.querySelectorAll('#category-rows tr[data-search]').length===0});</script>
@endsection

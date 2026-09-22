@extends('layouts.app')
@section('title', 'Categorías | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Categorías</h2><p>Consulta las categorías y controla cuáles están habilitadas para el menú.</p></div></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header"><div><h3>Listado de categorías</h3><span id="categories-count">{{ $categories->count() }} registradas</span></div><div class="category-tools"><input id="category-search" class="admin-search" type="search" placeholder="Buscar categoría..." aria-label="Buscar categoría"><select id="category-status" aria-label="Filtrar estado"><option value="">Todos</option><option value="active">Activas</option><option value="inactive">Inactivas</option></select></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Orden</th><th>Nombre</th><th>Descripción</th><th>Disponibilidad</th></tr></thead><tbody id="category-rows">
            @forelse ($categories as $category)
                <tr data-search="{{ strtolower($category->name.' '.($category->description??'')) }}" data-status="{{ $category->is_active?'active':'inactive' }}"><td><strong>{{ $category->sort_order }}</strong></td><td><strong>{{ $category->name }}</strong></td><td>{{ $category->description ?: 'Sin descripción' }}</td><td><form method="POST" action="{{ route('admin.categories.availability',$category) }}" class="availability-form">@csrf @method('PUT')<button type="submit" class="availability-toggle {{ $category->is_active ? 'is-available' : 'is-unavailable' }}">{{ $category->is_active ? '● Habilitada' : '○ Deshabilitada' }}</button></form></td></tr>
            @empty<tr><td colspan="4" class="empty-state"><h3>No hay categorías</h3><p>Las categorías se crean y administran desde Configuración.</p><a class="button button-primary" href="{{ route('admin.categories.create') }}">Nueva categoría</a></td></tr>@endforelse
        </tbody></table></div>
        <div id="categories-empty" class="empty-state" hidden><h3>Sin resultados</h3><p>No encontramos categorías con esos filtros.</p></div>
    </section>
</div>
<style>.category-tools{display:flex;gap:8px}.admin-search,.category-tools select{min-height:36px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.admin-search{width:220px}.data-table tr[hidden]{display:none}.availability-form{margin:0}.availability-toggle{border:1px solid transparent;border-radius:999px;padding:7px 11px;cursor:pointer;font:inherit;font-size:.8rem;font-weight:800}.availability-toggle.is-available{background:#e8f4df;color:#315d24;border-color:#b9d9a9}.availability-toggle.is-unavailable{background:#eee7d4;color:#6b5d49;border-color:#d9ccb0}.availability-toggle:hover{filter:brightness(.97)}@media(max-width:650px){.category-tools{width:100%;display:grid;grid-template-columns:1fr 1fr}.admin-search{width:100%;grid-column:1/-1}}
</style>
<script>const cs=document.getElementById('category-search'),cf=document.getElementById('category-status'),ce=document.getElementById('categories-empty'),cc=document.getElementById('categories-count');function filterCategories(){const q=cs.value.trim().toLowerCase(),f=cf.value;let shown=0;document.querySelectorAll('#category-rows tr[data-search]').forEach(r=>{const ok=(!q||r.dataset.search.includes(q))&&(!f||r.dataset.status===f);r.hidden=!ok;if(ok)shown++});cc.textContent=`${shown} ${shown===1?'registrada':'registradas'}${q||f?' encontradas':''}`;ce.hidden=shown!==0||document.querySelectorAll('#category-rows tr[data-search]').length===0}cs?.addEventListener('input',filterCategories);cf?.addEventListener('change',filterCategories);</script>
@endsection

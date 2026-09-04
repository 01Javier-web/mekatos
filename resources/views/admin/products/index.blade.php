@extends('layouts.app')
@section('title', 'Productos | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Productos</h2><p>Administra el catálogo que aparece en el menú y en los pedidos.</p></div><a class="button button-primary" href="{{ route('admin.products.create') }}">+ Nuevo producto</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header"><div><h3>Catálogo</h3><span>{{ $products->count() }} productos registrados</span></div><div class="filter-form"><input id="product-search" class="admin-search" type="search" placeholder="Buscar producto..."><select id="availability-filter" aria-label="Filtrar disponibilidad"><option value="">Todos</option><option value="available">Disponibles</option><option value="hidden">No disponibles</option></select></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Disponibilidad</th><th class="actions-cell">Acciones</th></tr></thead><tbody id="product-rows">
            @forelse ($products as $product)
                <tr data-search="{{ strtolower($product->name.' '.($product->description??'')) }}" data-availability="{{ $product->is_available?'available':'hidden' }}"><td><strong>{{ $product->name }}</strong><small>{{ $product->description ?: 'Sin descripción' }}</small></td><td>{{ $product->category?->name ?? 'Sin categoría' }}</td><td><strong>${{ number_format($product->price,0,',','.') }}</strong></td><td><span class="status {{ $product->is_available?'status-active':'status-inactive' }}">{{ $product->is_available?'Disponible':'No disponible' }}</span></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.products.edit',$product) }}">Editar</a><form method="POST" action="{{ route('admin.products.destroy',$product) }}" class="inline-form" onsubmit="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form></td></tr>
            @empty<tr><td colspan="5" class="empty-state"><h3>No hay productos</h3><p>Crea el primer producto del catálogo.</p><a class="button button-primary" href="{{ route('admin.products.create') }}">Nuevo producto</a></td></tr>@endforelse
        </tbody></table></div>
        <div id="products-empty" class="empty-state" hidden><h3>Sin resultados</h3><p>No encontramos productos con esos filtros.</p></div>
    </section>
</div>
<style>.admin-search{min-height:36px;width:220px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.data-table tr[hidden]{display:none}@media(max-width:760px){.filter-form{flex-wrap:wrap}.admin-search{width:100%}}</style>
<script>const ps=document.getElementById('product-search'),af=document.getElementById('availability-filter'),pe=document.getElementById('products-empty');function filterProducts(){const q=ps.value.trim().toLowerCase(),f=af.value;let shown=0;document.querySelectorAll('#product-rows tr[data-search]').forEach(r=>{const ok=(!q||r.dataset.search.includes(q))&&(!f||r.dataset.availability===f);r.hidden=!ok;if(ok)shown++});pe.hidden=shown!==0||document.querySelectorAll('#product-rows tr[data-search]').length===0}ps?.addEventListener('input',filterProducts);af?.addEventListener('change',filterProducts);</script>
@endsection

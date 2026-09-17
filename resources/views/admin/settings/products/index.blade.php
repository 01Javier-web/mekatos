@extends('layouts.app')
@section('title', 'Configuración · Productos | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div><span class="eyebrow">Configuración</span><h2>Gestión de productos</h2><p>Aquí puedes crear, editar o eliminar productos del catálogo.</p></div>
        <div class="page-heading-actions"><a class="button" href="{{ route('admin.settings') }}">← Configuración</a><a class="button button-primary" href="{{ route('admin.settings.products.create') }}">+ Nuevo producto</a></div>
    </div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>Revisa los datos:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header"><div><h3>Productos registrados</h3><span>{{ $products->count() }} productos</span></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Producto</th><th>Categoría</th><th>Precio</th><th>Disponibilidad</th><th class="actions-cell">Acciones</th></tr></thead><tbody>
            @forelse ($products as $product)
                <tr><td><strong>{{ $product->name }}</strong><small>{{ $product->description ?: 'Sin descripción' }}</small></td><td>{{ $product->category?->name ?? 'Sin categoría' }}</td><td><strong>${{ number_format($product->price,0,',','.') }}</strong></td><td><span class="status {{ $product->is_available?'status-active':'status-inactive' }}">{{ $product->is_available?'Disponible':'No disponible' }}</span></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.settings.products.edit',$product) }}">Editar</a><form method="POST" action="{{ route('admin.settings.products.destroy',$product) }}" class="inline-form" onsubmit="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form></td></tr>
            @empty<tr><td colspan="5" class="empty-state"><h3>No hay productos</h3><p>Crea el primer producto del catálogo.</p><a class="button button-primary" href="{{ route('admin.settings.products.create') }}">Nuevo producto</a></td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
<style>.page-heading-actions{display:flex;gap:8px;align-items:center}.actions-cell{white-space:nowrap}.inline-form{display:inline-block;margin-left:5px}@media(max-width:650px){.page-heading-actions{width:100%;flex-wrap:wrap}.page-heading-actions .button{flex:1}.actions-cell{white-space:normal}.inline-form{margin:5px 0 0}}</style>
@endsection

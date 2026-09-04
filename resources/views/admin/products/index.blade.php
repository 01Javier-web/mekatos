@extends('layouts.app')

@section('title', 'Productos | Mekatos')

@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración</span>
            <h2>Productos</h2>
            <p>Administra los productos disponibles en el menú de Mekatos.</p>
        </div>
        <a class="button button-primary" href="{{ route('admin.products.create') }}">+ Nuevo producto</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            <strong>Revisa los datos:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h3>Listado de productos</h3>
                <span>{{ $products->count() }} registrados</span>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Disponibilidad</th>
                        <th class="actions-cell">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                <strong>{{ $product->name }}</strong>
                                <small>{{ $product->description ?: 'Sin descripción' }}</small>
                            </td>
                            <td>{{ $product->category?->name ?? 'Sin categoría' }}</td>
                            <td>${{ number_format($product->price, 0, ',', '.') }}</td>
                            <td>
                                <span class="status {{ $product->is_available ? 'status-active' : 'status-inactive' }}">
                                    {{ $product->is_available ? 'Disponible' : 'No disponible' }}
                                </span>
                            </td>
                            <td class="actions-cell">
                                <a class="button button-small" href="{{ route('admin.products.edit', $product) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="inline-form" onsubmit="return confirm('¿Eliminar este producto?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-small button-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Todavía no hay productos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

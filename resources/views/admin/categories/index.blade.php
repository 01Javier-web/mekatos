@extends('layouts.app')

@section('title', 'Categorías | Mekatos')

@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración</span>
            <h2>Categorías</h2>
            <p>Organiza las categorías que aparecen en el menú de Mekatos.</p>
        </div>
        <a class="button button-primary" href="{{ route('admin.categories.create') }}">+ Nueva categoría</a>
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
                <h3>Listado de categorías</h3>
                <span>{{ $categories->count() }} registradas</span>
            </div>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th class="actions-cell">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->sort_order }}</td>
                            <td><strong>{{ $category->name }}</strong></td>
                            <td>{{ $category->description ?: 'Sin descripción' }}</td>
                            <td>
                                <span class="status {{ $category->is_active ? 'status-active' : 'status-inactive' }}">
                                    {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="actions-cell">
                                <a class="button button-small" href="{{ route('admin.categories.edit', $category) }}">Editar</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="inline-form" onsubmit="return confirm('¿Eliminar esta categoría?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-small button-danger" type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Todavía no hay categorías registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

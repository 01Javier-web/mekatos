@extends('layouts.app')

@section('title', 'Editar categoría | Mekatos')

@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración</span>
            <h2>Editar categoría</h2>
            <p>Actualiza la información de {{ $category->name }}.</p>
        </div>
        <a class="button" href="{{ route('admin.categories.index') }}">Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="form-card" method="POST" action="{{ route('admin.categories.update', $category) }}">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <label>
                <span>Nombre</span>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required maxlength="255">
            </label>

            <label>
                <span>Orden de aparición</span>
                <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" required>
            </label>
        </div>

        <label>
            <span>Descripción</span>
            <textarea name="description" rows="4">{{ old('description', $category->description) }}</textarea>
        </label>

        <label class="checkbox-label">
            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
            <span>Categoría activa</span>
        </label>

        <div class="form-actions">
            <a class="button" href="{{ route('admin.categories.index') }}">Cancelar</a>
            <button class="button button-primary" type="submit">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection

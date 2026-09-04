@extends('layouts.app')

@section('title', 'Nuevo producto | Mekatos')

@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración</span>
            <h2>Nuevo producto</h2>
            <p>Agrega un producto al menú de Mekatos.</p>
        </div>
        <a class="button" href="{{ route('admin.products.index') }}">Volver</a>
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

    <form class="form-card" method="POST" action="{{ route('admin.products.store') }}">
        @csrf

        <div class="form-grid">
            <label>
                <span>Nombre</span>
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
            </label>

            <label>
                <span>Categoría</span>
                <select name="category_id" required>
                    <option value="">Selecciona una categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>Precio</span>
                <input type="number" name="price" value="{{ old('price') }}" min="0" step="0.01" required>
            </label>

            <label>
                <span>Ruta de imagen</span>
                <input type="text" name="image_path" value="{{ old('image_path') }}" maxlength="255" placeholder="images/producto.jpg">
            </label>
        </div>

        <label>
            <span>Descripción</span>
            <textarea name="description" rows="4">{{ old('description') }}</textarea>
        </label>

        <label class="checkbox-label">
            <input type="checkbox" name="is_available" value="1" {{ old('is_available', true) ? 'checked' : '' }}>
            <span>Producto disponible</span>
        </label>

        <div class="form-actions">
            <a class="button" href="{{ route('admin.products.index') }}">Cancelar</a>
            <button class="button button-primary" type="submit">Guardar producto</button>
        </div>
    </form>
</div>
@endsection

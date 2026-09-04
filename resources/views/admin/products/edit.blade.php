@extends('layouts.app')

@section('title', 'Editar producto | Mekatos')

@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración</span>
            <h2>Editar producto</h2>
            <p>Actualiza la información de {{ $product->name }}.</p>
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

    <form class="form-card" method="POST" action="{{ route('admin.products.update', $product) }}">
        @csrf
        @method('PUT')

        <div class="form-grid">
            <label>
                <span>Nombre</span>
                <input type="text" name="name" value="{{ old('name', $product->name) }}" required maxlength="255">
            </label>

            <label>
                <span>Categoría</span>
                <select name="category_id" required>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>Precio</span>
                <input type="number" name="price" value="{{ old('price', $product->price) }}" min="0" step="0.01" required>
            </label>

            <label>
                <span>Ruta de imagen</span>
                <input type="text" name="image_path" value="{{ old('image_path', $product->image_path) }}" maxlength="255" placeholder="images/producto.jpg">
            </label>
        </div>

        <label>
            <span>Descripción</span>
            <textarea name="description" rows="4">{{ old('description', $product->description) }}</textarea>
        </label>

        <label class="checkbox-label">
            <input type="checkbox" name="is_available" value="1" {{ old('is_available', $product->is_available) ? 'checked' : '' }}>
            <span>Producto disponible</span>
        </label>

        <div class="form-actions">
            <a class="button" href="{{ route('admin.products.index') }}">Cancelar</a>
            <button class="button button-primary" type="submit">Guardar cambios</button>
        </div>
    </form>
</div>
@endsection

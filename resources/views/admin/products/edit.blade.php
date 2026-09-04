@extends('layouts.app')
@section('title', 'Editar producto | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Editar producto</h2><p>Actualiza la información de {{ $product->name }}.</p></div><a class="button" href="{{ route('admin.products.index') }}">← Volver</a></div>
    @if ($errors->any())<div class="alert alert-error"><strong>Revisa los datos del producto.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="form-card product-form" method="POST" action="{{ route('admin.products.update', $product) }}">
        @csrf @method('PUT')
        <div class="form-section-title"><strong>Información básica</strong><span>Actualiza los datos que verá el cliente.</span></div>
        <div class="form-grid">
            <label><span>Nombre</span><input type="text" name="name" value="{{ old('name', $product->name) }}" required maxlength="255" autofocus></label>
            <label><span>Categoría</span><select name="category_id" required>@foreach ($categories as $category)<option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>@endforeach</select></label>
        </div>
        <div class="form-grid">
            <label><span>Precio</span><input type="number" name="price" value="{{ old('price', $product->price) }}" min="0" step="0.01" required></label>
            <label><span>Ruta de imagen <small>(opcional)</small></span><input id="image-path" type="text" name="image_path" value="{{ old('image_path', $product->image_path) }}" maxlength="255" placeholder="images/producto.jpg"><small class="field-help">Ruta relativa dentro de <code>public/</code>.</small></label>
        </div>
        <label><span>Descripción <small>(opcional)</small></span><textarea id="description" name="description" rows="4" maxlength="1000" placeholder="Describe brevemente el producto.">{{ old('description', $product->description) }}</textarea><small class="field-help"><span id="description-count">0</span>/1000 caracteres</small></label>
        <div id="image-preview-wrap" class="image-preview-wrap" hidden><img id="image-preview" alt="Vista previa del producto"><span>Vista previa</span></div>
        <label class="checkbox-label"><input type="checkbox" name="is_available" value="1" {{ old('is_available', $product->is_available) ? 'checked' : '' }}><span>Producto disponible para vender</span></label>
        <div class="form-actions"><a class="button" href="{{ route('admin.products.index') }}">Cancelar</a><button class="button button-primary" type="submit" id="save-product">Guardar cambios</button></div>
    </form>
</div>
<style>.form-section-title{display:flex;justify-content:space-between;gap:15px;margin:-2px 0 20px;padding-bottom:12px;border-bottom:1px solid #eee}.form-section-title strong{font-size:.9rem}.form-section-title span{color:#888;font-size:.75rem}.field-help{display:block;margin-top:5px;color:#888;font-size:.72rem}.field-help code{font-size:.7rem}.product-form textarea{min-height:110px}.image-preview-wrap{position:relative;width:150px;height:120px;margin:-3px 0 20px;overflow:hidden;border:1px solid #e3e3e0;border-radius:12px;background:#f4f4f2}.image-preview-wrap img{width:100%;height:100%;object-fit:cover}.image-preview-wrap span{position:absolute;left:7px;bottom:7px;padding:3px 6px;border-radius:5px;background:rgba(23,23,23,.78);color:#fff;font-size:.65rem}@media(max-width:600px){.form-section-title{align-items:flex-start;flex-direction:column;gap:3px}}
</style>
<script>const description=document.getElementById('description'),descriptionCount=document.getElementById('description-count'),imagePath=document.getElementById('image-path'),imageWrap=document.getElementById('image-preview-wrap'),imagePreview=document.getElementById('image-preview'),form=document.querySelector('.product-form'),saveButton=document.getElementById('save-product');function updateDescriptionCount(){descriptionCount.textContent=description.value.length}function updateImagePreview(){const value=imagePath.value.trim();if(!value){imageWrap.hidden=true;imagePreview.removeAttribute('src');return}imagePreview.src=`{{ asset('') }}${value.replace(/^\/+/, '')}`;imageWrap.hidden=false;imagePreview.onerror=()=>{imageWrap.hidden=true}}description.addEventListener('input',updateDescriptionCount);imagePath.addEventListener('input',updateImagePreview);form.addEventListener('submit',()=>{saveButton.disabled=true;saveButton.textContent='Guardando...'});updateDescriptionCount();updateImagePreview();</script>
@endsection

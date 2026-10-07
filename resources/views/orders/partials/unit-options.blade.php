{{-- Opciones por unidad (jugos, opciones de bebida y combos), separadas de las salsas. Con cantidad > 1
     se elige "Todos iguales" (los selectores de siempre del producto) o "Personalizar individualmente":
     el navegador copia esos mismos selectores para cada unidad como unit_options[producto][unidad][campo].
     El precio y la nota de cada unidad se calculan en el backend (OrderUnitOptions). --}}
@php $optionMode = old('option_mode.'.$productId, \App\Support\OrderUnitOptions::MODE_SAME); @endphp
<div class="unit-options" data-unit-options data-product-id="{{ $productId }}" data-old-units='@json(old('unit_options.'.$productId, []))' hidden>
    <label class="sauce-mode">Opciones
        <select name="option_mode[{{ $productId }}]" data-option-mode disabled>
            <option value="{{ \App\Support\OrderUnitOptions::MODE_SAME }}">Todos iguales</option>
            <option value="{{ \App\Support\OrderUnitOptions::MODE_EACH }}" @selected($optionMode === \App\Support\OrderUnitOptions::MODE_EACH)>Personalizar individualmente</option>
        </select>
    </label>
    <div class="option-units" data-option-units></div>
</div>

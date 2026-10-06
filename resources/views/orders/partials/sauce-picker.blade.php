{{-- Salsas de un producto (PARA_LLEVAR / DOMICILIO). Cada salsa: no, EN EL PRODUCTO o APARTE. Sin cantidades.
     Con cantidad > 1 se elige "Todos iguales" o "Personalizar individualmente" (una configuración por unidad).
     Las opciones se generan en el navegador desde la plantilla única de la página (sauce-options-template),
     solo para los productos que se están pidiendo, para no repetir 17 salsas en cada producto. --}}
@php $sauceMode = old('sauce_mode.'.$productId, \App\Support\OrderSauces::MODE_SAME); @endphp
<details class="sauce-picker" data-sauce-picker data-product-id="{{ $productId }}" data-old-same='@json(old('sauces.'.$productId, []))' data-old-units='@json(old('unit_sauces.'.$productId, []))' hidden>
    <summary>＋ Agregar salsas</summary>
    <label class="sauce-mode" data-sauce-mode-wrap hidden>Salsas
        <select name="sauce_mode[{{ $productId }}]" data-sauce-mode>
            <option value="{{ \App\Support\OrderSauces::MODE_SAME }}">Todos iguales</option>
            <option value="{{ \App\Support\OrderSauces::MODE_EACH }}" @selected($sauceMode === \App\Support\OrderSauces::MODE_EACH)>Personalizar individualmente</option>
        </select>
    </label>
    <div class="sauce-options" data-sauce-same></div>
    <div class="sauce-units" data-sauce-units></div>
</details>

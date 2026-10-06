{{-- Plantilla única con las salsas activas: el navegador la copia en cada producto o unidad
     reemplazando __NAME__ por sauces[producto] o unit_sauces[producto][unidad]. --}}
<template id="sauce-options-template">
    @foreach($sauces as $sauce)
        <label class="sauce-option"><span>{{ $sauce->name }}</span>
            <select name="__NAME__[{{ $sauce->id }}]" data-sauce-id="{{ $sauce->id }}" aria-label="Salsa {{ $sauce->name }}">
                <option value="">—</option>
                @foreach(\App\Models\OrderSauce::PLACEMENTS as $placement => $label)
                    <option value="{{ $placement }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    @endforeach
</template>

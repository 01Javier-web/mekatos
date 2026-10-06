{{-- Salsas generales del pedido: no van asociadas a un producto y siempre se entregan aparte. Sin cantidades. --}}
<div class="general-sauces" data-general-sauces @if(! ($visible ?? false)) hidden @endif>
    <details>
        <summary>＋ Agregar salsas</summary>
        <p class="muted">Salsas generales del pedido (siempre van aparte).</p>
        <div class="general-sauce-list">
            @foreach($sauces as $sauce)
                <label><input type="checkbox" name="general_sauces[]" value="{{ $sauce->id }}" @checked(in_array((string) $sauce->id, array_map('strval', (array) old('general_sauces', [])), true))> {{ $sauce->name }}</label>
            @endforeach
        </div>
    </details>
</div>

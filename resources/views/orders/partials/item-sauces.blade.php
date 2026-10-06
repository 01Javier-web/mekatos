{{-- Salsas de una línea de pedido: cuáles van EN EL PRODUCTO y cuáles APARTE. --}}
@php
    $tag = $tag ?? 'div';
    $itemSauces = $item->sauces ?? collect();
    $inProduct = $itemSauces->where('placement', \App\Models\OrderSauce::IN_PRODUCT)->map(fn ($s) => $s->sauce?->name)->filter()->implode(', ');
    $onSide = $itemSauces->where('placement', \App\Models\OrderSauce::ON_SIDE)->map(fn ($s) => $s->sauce?->name)->filter()->implode(', ');
@endphp
@if($inProduct !== '')<{{ $tag }} class="{{ $class }}">Salsas en producto: {{ $inProduct }}</{{ $tag }}>@endif
@if($onSide !== '')<{{ $tag }} class="{{ $class }}">Salsas aparte: {{ $onSide }}</{{ $tag }}>@endif

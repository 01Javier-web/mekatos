{{-- "❌ NO PREPARAR": líneas (y salsas generales) que ya se habían enviado a cocina y una edición o
     cancelación anuló. --}}
<div class="change-title">❌ NO PREPARAR</div>
@foreach($voids as $item)
    <div class="line void-line">
        <span class="line-name">{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</span>
        @if($withPrice ?? false)<span class="line-price">&#36;{{ number_format($item->total, 0, ',', '.') }}</span>@endif
    </div>
    @if($item->notes)<div class="line-note">Detalle: {{ $item->notes }}</div>@endif
    @if($item->paired_order_item_id)<div class="line-note">Acompañaba a: {{ $item->pairedOrderItem?->product?->name ?? 'Producto' }}</div>@endif
    @include('orders.partials.item-sauces', ['item' => $item, 'class' => 'line-note'])
@endforeach
@if(($generalVoids ?? collect())->isNotEmpty())
    <div class="line void-line"><span class="line-name">Salsas generales (aparte): {{ $generalVoids->map(fn ($s) => $s->sauce?->name)->filter()->implode(', ') }}</span></div>
@endif

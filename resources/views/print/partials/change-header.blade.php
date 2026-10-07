{{-- Encabezado de una comanda de cambio (edición de un pedido ya enviado a cocina). --}}
<div class="takeaway-label">CAMBIO · PEDIDO #{{ $order->id }} · RONDA {{ $roundNumber }}</div>
@if($roundCreatedBy ?? null)<div class="meta"><div><strong>Cambiado por:</strong> {{ $roundCreatedBy }}</div></div>@endif

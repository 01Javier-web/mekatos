@extends('layouts.app')
@section('title', 'Pedido #'.$order->id.' | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Operación</span><h2>Pedido #{{ $order->id }}</h2><p>{{ $order->type?->value === 'PARA_LLEVAR' ? '🥡 Para llevar' : '🪑 Mesa '.($order->tableSession?->restaurantTable?->number ?? '—') }} · {{ $order->created_at?->format('d/m/Y H:i') }}</p></div><a class="button" href="{{ route('admin.orders.index') }}">← Volver a pedidos</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo actualizar el pedido.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="order-status-banner status-banner-{{ strtolower($order->status->value) }}">
        <div><span class="eyebrow">Estado actual</span><strong>{{ $order->status->value }}</strong><small>{{ match($order->status->value){'PENDIENTE'=>'El pedido está esperando que cocina lo tome.','PREPARANDO'=>'Cocina está preparando este pedido.','LISTO'=>'El pedido está listo para ser entregado.','ENTREGADO'=>'El pedido ya fue entregado.','CANCELADO'=>'Este pedido fue cancelado.'} }}</small></div>
        <div class="order-progress" aria-label="Progreso del pedido"><span class="{{ in_array($order->status->value,['PENDIENTE','PREPARANDO','LISTO','ENTREGADO'])?'done':'' }}">1</span><i></i><span class="{{ in_array($order->status->value,['PREPARANDO','LISTO','ENTREGADO'])?'done':'' }}">2</span><i></i><span class="{{ in_array($order->status->value,['LISTO','ENTREGADO'])?'done':'' }}">3</span><i></i><span class="{{ $order->status->value==='ENTREGADO'?'done':'' }}">4</span></div>
    </section>

    <div class="order-detail-grid">
        <section class="panel"><div class="panel-header"><h3>Estado del pedido</h3><span>Gestiona el siguiente paso de la operación.</span></div><div class="detail-body">
            @if (auth()->user()->role->value === 'ADMIN')
                @php $nextStatuses = match($order->status) { \App\Enums\OrderStatus::PENDING => [\App\Enums\OrderStatus::PREPARING,\App\Enums\OrderStatus::CANCELLED], \App\Enums\OrderStatus::PREPARING => [\App\Enums\OrderStatus::READY,\App\Enums\OrderStatus::CANCELLED], default => [] }; @endphp
                @if(count($nextStatuses))<form method="POST" action="{{ route('admin.orders.status',$order) }}" class="status-form">@csrf @method('PUT')<label><span>Siguiente estado</span><select name="status">@foreach($nextStatuses as $status)<option value="{{ $status->value }}">{{ $status->value }}</option>@endforeach</select></label><button class="button button-primary" type="submit">Actualizar estado</button></form>@else<p class="muted">Este pedido no tiene más cambios de estado disponibles.</p>@endif
            @endif
            @if ($order->status->value === 'LISTO' && in_array(auth()->user()->role->value,['ADMIN','MESERO'],true))<form method="POST" action="{{ route('admin.orders.deliver',$order) }}"><button class="button button-primary" type="submit">Marcar como entregado</button></form>@endif
            <div class="order-meta"><div><span>Creado por</span><strong>{{ $order->handledBy?->name ?? 'Pedido QR' }}</strong></div><div><span>Tipo</span><strong>{{ $order->type?->value === 'PARA_LLEVAR' ? 'Para llevar' : 'En mesa' }}</strong></div></div>
            @if ($order->delivered_at)<p class="muted">Entregado el {{ $order->delivered_at->format('d/m/Y H:i') }}{{ $order->deliveredBy ? ' por '.$order->deliveredBy->name : '' }}.</p>@endif
        </div></section>
        <section class="panel"><div class="panel-header"><h3>Resumen del pedido</h3><span>{{ $order->orderItems->sum('quantity') }} {{ $order->orderItems->sum('quantity')===1?'unidad':'unidades' }}</span></div><div class="detail-body">
            @foreach($order->orderItems as $item)<div class="order-line"><div><strong>{{ $item->product?->name ?? 'Producto' }}</strong><span>{{ $item->quantity }} × ${{ number_format($item->unit_price,0,',','.') }}</span></div><strong>${{ number_format($item->total,0,',','.') }}</strong></div>@endforeach
            @if($order->notes)<div class="info-box" style="margin-top:16px"><strong>Notas para cocina</strong><br>{{ $order->notes }}</div>@endif
            <div class="totals"><div><span>Subtotal</span><strong>${{ number_format($order->subtotal,0,',','.') }}</strong></div><div><span>Impuestos</span><strong>${{ number_format($order->tax,0,',','.') }}</strong></div><div class="total-row"><span>Total</span><strong>${{ number_format($order->total,0,',','.') }}</strong></div></div>
        </div></section>
    </div>
    <section class="panel history-panel"><div class="panel-header"><h3>Historial de estados</h3><span>Seguimiento de la preparación y entrega.</span></div><div class="detail-body">@forelse($order->statusHistories as $history)<div class="history-item"><strong>{{ $history->new_status }}</strong><span>{{ $history->changed_at?->format('d/m/Y H:i') }}{{ $history->changedBy ? ' · '.$history->changedBy->name : '' }}</span></div>@empty<p class="muted">Sin historial disponible.</p>@endforelse</div></section>
</div>
<style>
.order-status-banner{display:flex;align-items:center;justify-content:space-between;gap:25px;margin:-6px 0 20px;padding:17px 19px;border:1px solid #e5e5e2;border-radius:14px;background:#fff}.order-status-banner>div:first-child{min-width:0}.order-status-banner strong,.order-status-banner small{display:block}.order-status-banner strong{font-size:1.35rem;letter-spacing:-.03em}.order-status-banner small{margin-top:3px;color:#777}.status-banner-pendiente{border-color:#eadfbe;background:#fffdf6}.status-banner-preparando{border-color:#d8def5;background:#fafbff}.status-banner-listo{border-color:#cce3d3;background:#f9fdf9}.status-banner-entregado{border-color:#e1e1df}.status-banner-cancelado{border-color:#eccaca;background:#fffafa}.order-progress{display:flex;align-items:center;min-width:220px}.order-progress span{width:27px;height:27px;display:grid;place-items:center;border:1px solid #d8d8d5;border-radius:50%;background:#fff;color:#888;font-size:.7rem;font-weight:800}.order-progress span.done{background:#171717;border-color:#171717;color:#fff}.order-progress i{height:1px;flex:1;background:#ddd}.order-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:20px 0}.order-meta>div{padding:12px;border:1px solid #e7e7e4;border-radius:10px;background:#fafaf8}.order-meta span{display:block;color:#777;font-size:.74rem;margin-bottom:3px}.order-meta strong{font-size:.88rem}@media(max-width:650px){.order-status-banner{align-items:flex-start;flex-direction:column}.order-progress{width:100%;min-width:0}.order-meta{grid-template-columns:1fr}}
</style>
@endsection

@extends('layouts.app')
@section('title', 'Pedido #'.$order->id.' | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Operación</span><h2>Pedido #{{ $order->id }}</h2><p>{{ $order->type?->value === 'PARA_LLEVAR' ? '🥡 Para llevar' : '🪑 Mesa '.($order->tableSession?->restaurantTable?->number ?? '—') }} · {{ $order->created_at?->format('d/m/Y H:i') }}</p></div><a class="button" href="{{ route('admin.orders.index') }}">← Volver a pedidos</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="order-status-banner status-banner-{{ strtolower(str_replace(' ','-',$order->status->value)) }}">
        <div><span class="eyebrow">Estado actual</span><strong>{{ $order->status->value }}</strong><small>{{ match($order->status->value){'PENDIENTE'=>'Esperando que caja imprima las comandas.','EN PREPARACIÓN'=>'Las comandas fueron impresas y el pedido está en preparación.','ENTREGADO'=>'El pedido ya fue entregado y está pendiente de pago.','TERMINADO'=>'El pedido fue pagado y cerrado.'} }}</small></div>
        <div class="order-progress" aria-label="Progreso del pedido"><span class="{{ in_array($order->status->value,['PENDIENTE','EN PREPARACIÓN','ENTREGADO','TERMINADO'])?'done':'' }}">1</span><i></i><span class="{{ in_array($order->status->value,['EN PREPARACIÓN','ENTREGADO','TERMINADO'])?'done':'' }}">2</span><i></i><span class="{{ in_array($order->status->value,['ENTREGADO','TERMINADO'])?'done':'' }}">3</span><i></i><span class="{{ $order->status->value==='TERMINADO'?'done':'' }}">4</span></div>
    </section>
    <div class="order-detail-grid">
        <section class="panel"><div class="panel-header"><h3>Acciones</h3><span>El pedido sigue el flujo operativo de Mekatos.</span></div><div class="detail-body actions-stack">
            @if($order->status === \App\Enums\OrderStatus::PENDING)<a class="button button-primary" href="{{ route('admin.orders.print',$order) }}">🖨️ Imprimir comandas</a>@endif
            @if($order->status === \App\Enums\OrderStatus::PREPARING)
                <form method="POST" action="{{ route('admin.orders.deliver',$order) }}" onsubmit="return confirm('¿Confirmas que este pedido ya fue entregado?');">@csrf @method('PUT')<button class="button button-primary" type="submit">✓ Marcar como entregado</button></form>
                <p class="muted">Las comandas ya fueron impresas. Cuando el pedido llegue a la mesa o se entregue al cliente, márcalo como ENTREGADO.</p>
            @endif
            @if($order->status === \App\Enums\OrderStatus::DELIVERED)
                @if($order->type?->value === 'PARA_LLEVAR')
                    <form method="POST" action="{{ route('admin.orders.pay',$order) }}" onsubmit="return confirm('¿Confirmas que el pedido fue pagado?');">@csrf<button class="button button-primary" type="submit">💰 Registrar pago</button></form>
                @elseif($order->tableSession)
                    <a class="button button-primary" href="{{ route('admin.accounts.show',$order->tableSession) }}">💰 Ver / cobrar cuenta</a>
                @endif
            @endif
            @if($order->status === \App\Enums\OrderStatus::COMPLETED)<p class="muted">Pedido terminado{{ $order->paid_at ? ' el '.$order->paid_at->format('d/m/Y H:i') : '' }}{{ $order->paidBy ? ' por '.$order->paidBy->name : '' }}.</p>@endif
            <div class="order-meta"><div><span>Creado por</span><strong>{{ $order->handledBy?->name ?? 'Pedido QR' }}</strong></div><div><span>Tipo</span><strong>{{ $order->type?->value === 'PARA_LLEVAR' ? 'Para llevar' : 'En mesa' }}</strong></div></div>
            @if ($order->delivered_at)<p class="muted">Entregado el {{ $order->delivered_at->format('d/m/Y H:i') }}{{ $order->deliveredBy ? ' por '.$order->deliveredBy->name : '' }}.</p>@endif
        </div></section>
        <section class="panel"><div class="panel-header"><h3>Resumen del pedido</h3><span>{{ $order->orderItems->sum('quantity') }} {{ $order->orderItems->sum('quantity')===1?'unidad':'unidades' }}</span></div><div class="detail-body">
            @foreach($order->orderItems as $item)<div class="order-line"><div><strong>{{ $item->product?->name ?? 'Producto' }}</strong><span>{{ $item->quantity }} × ${{ number_format($item->unit_price,0,',','.') }}</span>@if($item->notes)<small class="item-note-display">⚠ {{ $item->notes }}</small>@endif</div><strong>${{ number_format($item->total,0,',','.') }}</strong></div>@endforeach
            @if($order->notes)<div class="info-box" style="margin-top:16px"><strong>Notas generales para cocina</strong><br>{{ $order->notes }}</div>@endif
            <div class="totals"><div><span>Subtotal</span><strong>${{ number_format($order->subtotal,0,',','.') }}</strong></div><div><span>Impuestos</span><strong>${{ number_format($order->tax,0,',','.') }}</strong></div><div class="total-row"><span>Total</span><strong>${{ number_format($order->total,0,',','.') }}</strong></div></div>
        </div></section>
    </div>
    <section class="panel history-panel"><div class="panel-header"><h3>Historial de estados</h3><span>Seguimiento de preparación, entrega y pago.</span></div><div class="detail-body">@forelse($order->statusHistories as $history)<div class="history-item"><strong>{{ $history->new_status }}</strong><span>{{ $history->changed_at?->format('d/m/Y H:i') }}{{ $history->changedBy ? ' · '.$history->changedBy->name : '' }}</span></div>@empty<p class="muted">Sin historial disponible.</p>@endforelse</div></section>
</div>
<style>
.order-status-banner{display:flex;align-items:center;justify-content:space-between;gap:25px;margin:-6px 0 20px;padding:17px 19px;border:1px solid #e5e5e2;border-radius:14px;background:#fff}.order-status-banner>div:first-child{min-width:0}.order-status-banner strong,.order-status-banner small{display:block}.order-status-banner strong{font-size:1.35rem;letter-spacing:-.03em}.order-status-banner small{margin-top:3px;color:#777}.status-banner-pendiente{border-color:#eadfbe;background:#fffdf6}.status-banner-en-preparación{border-color:#d8def5;background:#fafbff}.status-banner-entregado{border-color:#e1e1df}.status-banner-terminado{border-color:#cce3d3;background:#f9fdf9}.order-progress{display:flex;align-items:center;min-width:220px}.order-progress span{width:27px;height:27px;display:grid;place-items:center;border:1px solid #d8d8d5;border-radius:50%;background:#fff;color:#888;font-size:.7rem;font-weight:800}.order-progress span.done{background:#171717;border-color:#171717;color:#fff}.order-progress i{height:1px;flex:1;background:#ddd}.order-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:20px 0}.order-meta>div{padding:12px;border:1px solid #e7e7e4;border-radius:10px;background:#fafaf8}.order-meta span{display:block;color:#777;font-size:.74rem;margin-bottom:3px}.order-meta strong{font-size:.88rem}.item-note-display{display:block;margin-top:5px;color:#8a5a34;font-size:.75rem;font-weight:650;line-height:1.35}.actions-stack{display:flex;flex-direction:column;align-items:flex-start;gap:10px}.actions-stack form{margin:0}.actions-stack form+ .muted{margin-top:-3px}@media(max-width:650px){.order-status-banner{align-items:flex-start;flex-direction:column}.order-progress{width:100%;min-width:0}.order-meta{grid-template-columns:1fr}.actions-stack{align-items:stretch}.actions-stack .button{width:100%}}
</style>
@endsection

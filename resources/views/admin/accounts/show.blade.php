@extends('layouts.app')
@section('title', 'Cuenta Mesa '.$tableSession->restaurantTable?->number.' | Mekatos')
@section('content')
@php
    $backRoute = auth()->user()?->role?->value === 'ADMIN' ? 'admin.orders.index' : 'waiter.orders';
    $notReady = $orders->filter(fn($order) => ! $order->status->isCollectable());
@endphp
<div class="page-shell page-shell-narrow">
    <div class="page-heading">
        <div><span class="eyebrow">Cobro</span><h2>Cuenta · Mesa {{ $tableSession->restaurantTable?->number ?? '—' }}</h2><p>Todos los pedidos acumulados de esta mesa.</p></div>
        <a class="button" href="{{ route($backRoute) }}">← Volver</a>
    </div>
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="panel">
        <div class="panel-header"><div><h3>Consumo acumulado</h3><span>{{ $orders->count() }} {{ $orders->count() === 1 ? 'pedido' : 'pedidos' }}</span></div><strong class="account-total">${{ number_format($total,0,',','.') }}</strong></div>
        <div class="detail-body">
            @foreach($orders as $order)
                @php
                    $accountStatus = $order->status->operational()->value;
                @endphp
                <div class="account-order-status">
                    <div><span>Pedido #{{ $order->id }}</span><small>{{ $order->created_at?->format('H:i') ?? '—' }} · {{ $order->handledBy?->name ?? 'Pedido QR' }}</small></div>
                    @if($order->status->isCollectable())
                        <strong class="account-status account-status-delivered">{{ $accountStatus }}</strong>
                    @else
                        <strong class="account-status account-status-blocked">{{ $accountStatus }}</strong>
                    @endif
                </div>
                @foreach($order->orderItems as $item)
                    <div class="account-line">
                        <div><strong>{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</strong>@if($item->notes)<small>{{ $item->notes }}</small>@endif</div>
                        <strong>${{ number_format($item->total,0,',','.') }}</strong>
                    </div>
                @endforeach
            @endforeach
            <div class="totals"><div class="total-row"><span>Total</span><strong>${{ number_format($total,0,',','.') }}</strong></div></div>
            @if($notReady->isNotEmpty())
                <div class="info-box" style="margin-top:16px">
                    <strong>La cuenta todavía no puede cerrarse.</strong><br>
                    Hay pedidos que todavía no han sido enviados a cocina. Termina de imprimirlos antes de cobrar la mesa.
                </div>
            @else
                <div class="account-actions">
                    <a class="button" target="_blank" rel="noopener" href="{{ route('admin.accounts.print',$tableSession) }}">🧾 Imprimir cuenta</a>
                    @if(auth()->user()?->role === \App\UserRole::Admin)<form method="POST" action="{{ route('admin.accounts.pay',$tableSession) }}" onsubmit="return confirm('¿Confirmas que la cuenta de la Mesa {{ $tableSession->restaurantTable?->number }} fue pagada?');">@csrf<button class="button button-primary" type="submit">💰 Registrar pago y terminar</button></form>@endif
                </div>
            @endif
        </div>
    </section>
</div>
<style>
.account-total{font-size:1.25rem}.account-order-status{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:12px;padding:10px 12px;border-radius:8px;background:#f6f6f3;font-size:.78rem}.account-order-status>div:first-child span{font-weight:750}.account-order-status small{display:block;margin-top:3px;color:#888;font-size:.68rem}.account-status{padding:5px 9px;border-radius:999px;font-size:.68rem;font-weight:750}.account-status-delivered{background:#e9f3eb;color:#52735a}.account-status-blocked{background:#f9eeee;color:#9a5e5e}.account-order-status form{margin:0}.account-deliver-button{border:1px solid #c9e1cf;background:#edf6ef;color:#52735a;border-radius:8px;padding:8px 11px;font:inherit;font-size:.72rem;font-weight:800;cursor:pointer}.account-deliver-button:hover{background:#e2f0e5}.account-line{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid #eee}.account-line small{display:block;margin-top:3px;color:#777;font-size:.75rem}.account-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.account-actions form{margin:0}@media(max-width:600px){.account-order-status{align-items:flex-start;flex-direction:column}.account-order-status form,.account-deliver-button{width:100%}.account-actions>*{width:100%}.account-actions .button{width:100%}}
</style>
@endsection

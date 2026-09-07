@extends('layouts.app')
@section('title', 'Cuenta Mesa '.$tableSession->restaurantTable?->number.' | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading">
        <div><span class="eyebrow">Cobro</span><h2>Cuenta · Mesa {{ $tableSession->restaurantTable?->number ?? '—' }}</h2><p>Todos los pedidos acumulados de esta mesa.</p></div>
        <a class="button" href="{{ route('waiter.orders') }}">← Volver</a>
    </div>
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="panel">
        <div class="panel-header"><div><h3>Consumo acumulado</h3><span>{{ $orders->count() }} {{ $orders->count() === 1 ? 'pedido' : 'pedidos' }}</span></div><strong class="account-total">${{ number_format($total,0,',','.') }}</strong></div>
        <div class="detail-body">
            @foreach($orders as $order)
                @foreach($order->orderItems as $item)
                    <div class="account-line">
                        <div><strong>{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</strong>@if($item->notes)<small>{{ $item->notes }}</small>@endif</div>
                        <strong>${{ number_format($item->total,0,',','.') }}</strong>
                    </div>
                @endforeach
            @endforeach
            <div class="totals"><div class="total-row"><span>Total</span><strong>${{ number_format($total,0,',','.') }}</strong></div></div>
            @if($orders->contains(fn($order) => $order->status !== \App\Enums\OrderStatus::DELIVERED))
                <div class="info-box" style="margin-top:16px"><strong>La cuenta todavía no puede cerrarse.</strong><br>Todos los pedidos de la mesa deben estar ENTREGADOS antes de registrar el pago.</div>
            @else
                <div class="account-actions">
                    <a class="button" target="_blank" rel="noopener" href="{{ route('admin.accounts.print',$tableSession) }}">🧾 Imprimir cuenta</a>
                    <form method="POST" action="{{ route('admin.accounts.pay',$tableSession) }}" onsubmit="return confirm('¿Confirmas que la cuenta de la Mesa {{ $tableSession->restaurantTable?->number }} fue pagada?');">@csrf<button class="button button-primary" type="submit">💰 Registrar pago y terminar</button></form>
                </div>
            @endif
        </div>
    </section>
</div>
<style>.account-total{font-size:1.25rem}.account-line{display:flex;justify-content:space-between;gap:16px;padding:10px 0;border-bottom:1px solid #eee}.account-line small{display:block;margin-top:3px;color:#777;font-size:.75rem}.account-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.account-actions form{margin:0}@media(max-width:600px){.account-actions>*{width:100%}.account-actions .button{width:100%}}</style>
@endsection

@extends('layouts.app')
@section('title', 'Pedido #'.$order->id.' | Mekatos')
@section('content')
<div class="page-shell page-shell-narrow">
    <div class="page-heading"><div><span class="eyebrow">Operación</span><h2>Pedido #{{ $order->id }}</h2><p>Mesa {{ $order->tableSession?->restaurantTable?->number ?? '—' }} · {{ $order->created_at?->format('d/m/Y H:i') }}</p></div><a class="button" href="{{ route('admin.orders.index') }}">Volver a pedidos</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="order-detail-grid">
        <section class="panel"><div class="panel-header"><h3>Estado</h3><span>Estado actual: {{ $order->status->value }}</span></div><div class="detail-body">
            @if (auth()->user()->role->value === 'ADMIN')
                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="status-form">@csrf @method('PUT')<label><span>Cambiar estado</span><select name="status">@foreach ($statuses as $status)<option value="{{ $status->value }}" {{ $order->status === $status ? 'selected' : '' }}>{{ $status->value }}</option>@endforeach</select></label><button class="button button-primary" type="submit">Actualizar estado</button></form>
            @endif
            @if ($order->status->value === 'LISTO' && in_array(auth()->user()->role->value, ['ADMIN','MESERO'], true))
                <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">@csrf @method('PUT')<button class="button button-primary" type="submit">Marcar como entregado</button></form>
            @endif
            @if ($order->delivered_at)<p class="muted">Entregado el {{ $order->delivered_at->format('d/m/Y H:i') }}{{ $order->deliveredBy ? ' por '.$order->deliveredBy->name : '' }}.</p>@endif
        </div></section>
        <section class="panel"><div class="panel-header"><h3>Resumen</h3><span>{{ $order->orderItems->sum('quantity') }} unidades</span></div><div class="detail-body">
            @foreach ($order->orderItems as $item)<div class="order-line"><div><strong>{{ $item->product?->name ?? 'Producto' }}</strong><span>{{ $item->quantity }} × ${{ number_format($item->unit_price, 0, ',', '.') }}</span></div><strong>${{ number_format($item->total, 0, ',', '.') }}</strong></div>@endforeach
            <div class="totals"><div><span>Subtotal</span><strong>${{ number_format($order->subtotal, 0, ',', '.') }}</strong></div><div><span>Impuestos</span><strong>${{ number_format($order->tax, 0, ',', '.') }}</strong></div><div class="total-row"><span>Total</span><strong>${{ number_format($order->total, 0, ',', '.') }}</strong></div></div>
        </div></section>
    </div>
    <section class="panel history-panel"><div class="panel-header"><h3>Historial de estados</h3></div><div class="detail-body">
        @forelse ($order->statusHistories as $history)<div class="history-item"><strong>{{ $history->new_status }}</strong><span>{{ $history->changed_at?->format('d/m/Y H:i') }}{{ $history->changedBy ? ' · '.$history->changedBy->name : '' }}</span></div>@empty<p class="muted">Sin historial disponible.</p>@endforelse
    </div></section>
</div>
@endsection

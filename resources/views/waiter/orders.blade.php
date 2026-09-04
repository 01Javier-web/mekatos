@extends('layouts.app')
@section('title', 'Pedidos del mesero | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Mesero</span><h2>Pedidos activos</h2><p>Consulta los pedidos en proceso y entrega los que estén listos.</p></div></div>
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="waiter-grid">
        @forelse ($orders as $order)
            <article class="waiter-card">
                <div class="waiter-card-top"><div><span class="eyebrow">Pedido</span><h3>#{{ $order->id }}</h3></div><span class="status status-order">{{ $order->status->value }}</span></div>
                <p><strong>Mesa:</strong> {{ $order->tableSession?->restaurantTable?->number ?? '—' }}</p>
                <div class="waiter-items">@foreach ($order->orderItems as $item)<div><span>{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</span><strong>${{ number_format($item->total,0,',','.') }}</strong></div>@endforeach</div>
                <div class="waiter-total"><span>Total</span><strong>${{ number_format($order->total,0,',','.') }}</strong></div>
                @if ($order->status->value === 'LISTO')
                    <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">@csrf @method('PUT')<button class="button button-primary" type="submit">Entregar pedido</button></form>
                @endif
            </article>
        @empty
            <div class="panel empty-state">No hay pedidos activos en este momento.</div>
        @endforelse
    </div>
</div>
@endsection

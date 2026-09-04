@extends('layouts.app')
@section('title', 'Panel de meseros | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Operación</span>
            <h2>Panel de meseros</h2>
            <p>Gestiona los pedidos de las mesas y actualiza su estado durante el servicio.</p>
        </div>
        <a class="button" href="{{ route('waiter.orders') }}">Actualizar</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error">
            <strong>No se pudo completar la acción.</strong>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <section class="stats-grid" aria-label="Resumen de pedidos">
        <div class="stat-card"><span>Pedidos activos</span><strong>{{ $counts['total'] }}</strong></div>
        <div class="stat-card"><span>Pendientes</span><strong>{{ $counts['pending'] }}</strong></div>
        <div class="stat-card"><span>En preparación</span><strong>{{ $counts['preparing'] }}</strong></div>
        <div class="stat-card"><span>Listos para entregar</span><strong>{{ $counts['ready'] }}</strong></div>
    </section>

    <div class="panel-header" style="background:#fff;border:1px solid #e5e7eb;border-radius:12px 12px 0 0;">
        <h3>Pedidos activos</h3>
        <span>{{ $counts['total'] }} {{ $counts['total'] === 1 ? 'pedido' : 'pedidos' }} en seguimiento</span>
    </div>

    <div class="waiter-grid">
        @forelse ($orders as $order)
            @php $status = $order->status; @endphp
            <article class="waiter-card">
                <div class="waiter-card-top">
                    <div>
                        <span class="eyebrow">Pedido</span>
                        <h3>#{{ $order->id }}</h3>
                    </div>
                    <span class="status status-order">{{ $status->value }}</span>
                </div>

                <div style="display:flex;justify-content:space-between;gap:16px;margin:16px 0;padding:12px 0;border-top:1px solid #eee;border-bottom:1px solid #eee;">
                    <div><span class="muted" style="display:block;font-size:.8rem;">Mesa</span><strong>{{ $order->tableSession?->restaurantTable?->number ?? '—' }}</strong></div>
                    <div style="text-align:right;"><span class="muted" style="display:block;font-size:.8rem;">Hora</span><strong>{{ $order->created_at?->format('H:i') ?? '—' }}</strong></div>
                </div>

                @if ($order->notes)
                    <div class="info-box"><strong>Nota del cliente:</strong><br>{{ $order->notes }}</div>
                @endif

                <div class="waiter-items">
                    @foreach ($order->orderItems as $item)
                        <div>
                            <span><strong>{{ $item->quantity }}×</strong> {{ $item->product?->name ?? 'Producto' }}</span>
                            <strong>${{ number_format($item->total, 0, ',', '.') }}</strong>
                        </div>
                    @endforeach
                </div>

                <div class="waiter-total">
                    <span>Total</span>
                    <strong>${{ number_format($order->total, 0, ',', '.') }}</strong>
                </div>

                <div style="display:grid;gap:8px;">
                    @if ($status === \App\Enums\OrderStatus::PENDING)
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="PREPARANDO">
                            <button class="button button-primary" style="width:100%;" type="submit">Iniciar preparación</button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="CANCELADO">
                            <button class="button button-danger" style="width:100%;" type="submit">Cancelar pedido</button>
                        </form>
                    @elseif ($status === \App\Enums\OrderStatus::PREPARING)
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="LISTO">
                            <button class="button button-primary" style="width:100%;" type="submit">Marcar como listo</button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="CANCELADO">
                            <button class="button button-danger" style="width:100%;" type="submit">Cancelar pedido</button>
                        </form>
                    @elseif ($status === \App\Enums\OrderStatus::READY)
                        <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">
                            @csrf @method('PUT')
                            <button class="button button-primary" style="width:100%;" type="submit">Entregar pedido</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="panel empty-state" style="grid-column:1/-1;">
                <h3>No hay pedidos activos</h3>
                <p>Cuando entre un nuevo pedido aparecerá aquí al actualizar la página.</p>
                <a class="button" href="{{ route('waiter.orders') }}">Actualizar pedidos</a>
            </div>
        @endforelse
    </div>
</div>
@endsection

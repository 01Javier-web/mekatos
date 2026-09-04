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
            @php
                $status = $order->status;
                $statusClass = match ($status) {
                    \App\Enums\OrderStatus::PENDING => 'status-pending',
                    \App\Enums\OrderStatus::PREPARING => 'status-preparing',
                    \App\Enums\OrderStatus::READY => 'status-ready',
                    default => 'status-order',
                };
            @endphp
            <article class="waiter-card">
                <div class="waiter-card-top">
                    <div>
                        <span class="eyebrow">Pedido</span>
                        <h3>#{{ $order->id }}</h3>
                    </div>
                    <span class="status {{ $statusClass }}">{{ $status->value }}</span>
                </div>

                <div class="waiter-order-meta">
                    <div><span>Mesa</span><strong>{{ $order->tableSession?->restaurantTable?->number ?? '—' }}</strong></div>
                    <div><span>Hora</span><strong>{{ $order->created_at?->format('H:i') ?? '—' }}</strong></div>
                </div>

                @if ($order->notes)
                    <div class="waiter-note"><strong>Nota:</strong> {{ $order->notes }}</div>
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

                <div class="waiter-actions">
                    @if ($status === \App\Enums\OrderStatus::PENDING)
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="PREPARANDO">
                            <button class="button button-primary" type="submit">Iniciar preparación</button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="CANCELADO">
                            <button class="button button-danger" type="submit">Cancelar</button>
                        </form>
                    @elseif ($status === \App\Enums\OrderStatus::PREPARING)
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="LISTO">
                            <button class="button button-primary" type="submit">Marcar como listo</button>
                        </form>
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="status" value="CANCELADO">
                            <button class="button button-danger" type="submit">Cancelar</button>
                        </form>
                    @elseif ($status === \App\Enums\OrderStatus::READY)
                        <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">
                            @csrf @method('PUT')
                            <button class="button button-primary" type="submit">Entregar pedido</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="panel empty-state" style="grid-column:1/-1;">
                <h3>No hay pedidos activos</h3>
                <p>Cuando entre un nuevo pedido aparecerá aquí automáticamente al actualizar la página.</p>
                <a class="button" href="{{ route('waiter.orders') }}">Actualizar pedidos</a>
            </div>
        @endforelse
    </div>
</div>
@endsection

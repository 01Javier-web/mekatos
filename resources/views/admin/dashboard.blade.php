@extends('layouts.app')

@section('title', 'Inicio | Mekatos')

@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Panel administrativo</span>
            <h2>Inicio</h2>
            <p>Resumen general del sistema Mekatos.</p>
        </div>
    </div>

    <div class="stats-grid">
        <a class="stat-card" href="{{ route('admin.orders.index') }}"><span>Pedidos</span><strong>{{ $ordersCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.orders.index', ['status' => 'PENDIENTE']) }}"><span>Pendientes</span><strong>{{ $pendingOrders }}</strong></a>
        <a class="stat-card" href="{{ route('admin.orders.index', ['status' => 'LISTO']) }}"><span>Listos</span><strong>{{ $readyOrders }}</strong></a>
        <a class="stat-card" href="{{ route('admin.products.index') }}"><span>Productos</span><strong>{{ $productsCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.categories.index') }}"><span>Categorías</span><strong>{{ $categoriesCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.tables.index') }}"><span>Mesas</span><strong>{{ $tablesCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.users.index') }}"><span>Usuarios</span><strong>{{ $usersCount }}</strong></a>
    </div>

    <section class="panel dashboard-panel">
        <div class="panel-header"><h3>Pedidos recientes</h3><span>Últimos 6 pedidos registrados</span></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Pedido</th><th>Mesa</th><th>Estado</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td><strong>#{{ $order->id }}</strong></td>
                            <td>{{ $order->tableSession?->restaurantTable?->number ?? '—' }}</td>
                            <td><span class="status status-order">{{ $order->status->value }}</span></td>
                            <td>${{ number_format($order->total, 0, ',', '.') }}</td>
                            <td class="actions-cell"><a class="button button-small" href="{{ route('admin.orders.show', $order) }}">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Todavía no hay pedidos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

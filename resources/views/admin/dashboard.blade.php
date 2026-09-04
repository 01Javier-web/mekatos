@extends('layouts.app')

@section('title', 'Inicio | Mekatos')

@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Panel administrativo</span>
            <h2>Buenos días, {{ auth()->user()->name }}</h2>
            <p>Ten una vista rápida de la operación de Mekatos.</p>
        </div>
        <a class="button button-primary" href="{{ route('admin.orders.create') }}">+ Crear pedido</a>
    </div>

    <div class="stats-grid">
        <a class="stat-card" href="{{ route('admin.orders.index') }}"><span>Pedidos registrados</span><strong>{{ $ordersCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.orders.index', ['status' => 'PENDIENTE']) }}"><span>Esperando cocina</span><strong>{{ $pendingOrders }}</strong></a>
        <a class="stat-card" href="{{ route('admin.orders.index', ['status' => 'LISTO']) }}"><span>Listos para entregar</span><strong>{{ $readyOrders }}</strong></a>
        <a class="stat-card" href="{{ route('admin.products.index') }}"><span>Productos activos</span><strong>{{ $productsCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.categories.index') }}"><span>Categorías</span><strong>{{ $categoriesCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.tables.index') }}"><span>Mesas</span><strong>{{ $tablesCount }}</strong></a>
        <a class="stat-card" href="{{ route('admin.users.index') }}"><span>Usuarios</span><strong>{{ $usersCount }}</strong></a>
    </div>

    <section class="quick-actions" aria-label="Acciones rápidas">
        <a href="{{ route('admin.orders.create') }}"><span>＋</span><div><strong>Nuevo pedido</strong><small>Crear desde caja</small></div></a>
        <a href="{{ route('admin.orders.index') }}"><span>↗</span><div><strong>Ver pedidos</strong><small>Revisar operación</small></div></a>
        <a href="{{ route('admin.products.index') }}"><span>▦</span><div><strong>Menú</strong><small>Gestionar productos</small></div></a>
        <a href="{{ route('admin.tables.index') }}"><span>⌑</span><div><strong>Mesas</strong><small>Estado y QR</small></div></a>
    </section>

    <section class="panel dashboard-panel">
        <div class="panel-header order-filter-header">
            <div><h3>Pedidos recientes</h3><span>Los últimos pedidos registrados en el sistema.</span></div>
            <a class="button button-small" href="{{ route('admin.orders.index') }}">Ver todos</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Pedido</th><th>Atención</th><th>Estado</th><th>Total</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recentOrders as $order)
                        @php $isTakeaway = $order->type?->value === 'PARA_LLEVAR'; @endphp
                        <tr>
                            <td><strong>#{{ $order->id }}</strong><small>{{ $order->created_at?->format('d/m/Y H:i') }}</small></td>
                            <td>{{ $isTakeaway ? 'Para llevar' : 'Mesa '.($order->tableSession?->restaurantTable?->number ?? '—') }}</td>
                            <td><span class="status status-order">{{ $order->status->value }}</span></td>
                            <td><strong>${{ number_format($order->total, 0, ',', '.') }}</strong></td>
                            <td class="actions-cell"><a class="button button-small" href="{{ route('admin.orders.show', $order) }}">Ver detalle</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state"><h3>Aún no hay pedidos</h3><p>Los pedidos nuevos aparecerán aquí automáticamente.</p><a class="button button-primary" href="{{ route('admin.orders.create') }}">Crear primer pedido</a></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

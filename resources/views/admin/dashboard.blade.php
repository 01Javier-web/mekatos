@extends('layouts.app')

@section('title', 'Inicio | Mekatos')

@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div><span class="eyebrow">Panel administrativo</span><h2>Buenos días, {{ auth()->user()->name }}</h2><p>Ten una vista rápida de la operación de Mekatos.</p></div>
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
        <div class="panel-header order-filter-header"><div><h3>Pedidos recientes</h3><span>Los últimos pedidos registrados en el sistema.</span></div><a class="button button-small" href="{{ route('admin.orders.index') }}">Ver todos</a></div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Pedido</th><th>Atención</th><th>Estado</th><th>Total</th><th></th></tr></thead>
            <tbody>
                @forelse ($recentOrders as $order)
                    @php $isTakeaway = $order->type?->value === 'PARA_LLEVAR'; @endphp
                    <tr><td><strong>#{{ $order->id }}</strong><small>{{ $order->created_at?->format('d/m/Y H:i') }}</small></td><td>{{ $isTakeaway ? 'Para llevar' : 'Mesa '.($order->tableSession?->restaurantTable?->number ?? '—') }}</td><td><span class="status status-order">{{ $order->status->value }}</span></td><td><strong>${{ number_format($order->total, 0, ',', '.') }}</strong></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.orders.show', $order) }}">Ver detalle</a></td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state"><h3>Aún no hay pedidos</h3><p>Los pedidos nuevos aparecerán aquí.</p><a class="button button-primary" href="{{ route('admin.orders.create') }}">Crear primer pedido</a></td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>
<style>
.quick-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:-4px 0 24px}.quick-actions a{display:flex;align-items:center;gap:12px;padding:15px 16px;background:#fff;border:1px solid #e5e5e2;border-radius:14px;text-decoration:none;box-shadow:0 6px 18px rgba(0,0,0,.035)}.quick-actions a:hover{background:#fafafa;transform:translateY(-1px)}.quick-actions a>span{width:38px;height:38px;display:grid;place-items:center;border-radius:10px;background:#f0f0ee;font-size:1.2rem}.quick-actions strong,.quick-actions small{display:block}.quick-actions strong{font-size:.9rem}.quick-actions small{margin-top:2px;color:#777;font-size:.75rem}@media(max-width:900px){.quick-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.quick-actions{grid-template-columns:1fr}}
</style>
@endsection

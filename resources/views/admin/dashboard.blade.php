@extends('layouts.app')

@section('title', 'Inicio | Mekatos')

@section('content')
<div class="page-shell">
    <div class="page-heading dashboard-heading">
        <div><span class="eyebrow">Panel administrativo</span><h2>Buenos días, {{ auth()->user()->name }}</h2><p>Una vista rápida para saber qué necesita atención ahora.</p></div>
        <a class="button button-primary" href="{{ route('admin.orders.create') }}">+ Crear pedido</a>
    </div>

    <section class="dashboard-focus" aria-label="Estado de la operación">
        <div><span class="focus-dot"></span><div><strong>Operación activa</strong><small>Gestiona los pedidos desde este panel.</small></div></div>
        <a href="{{ route('admin.orders.index') }}">Ver todos los pedidos <span aria-hidden="true">→</span></a>
    </section>

    <div class="stats-grid">
        <a class="stat-card" href="{{ route('admin.orders.index') }}"><span>Pedidos registrados</span><strong>{{ $ordersCount }}</strong><small>Histórico del sistema</small></a>
        <a class="stat-card stat-attention" href="{{ route('admin.orders.index', ['status' => 'PENDIENTE']) }}"><span>Esperando cocina</span><strong>{{ $pendingOrders }}</strong><small>Requieren preparación</small></a>
        <a class="stat-card stat-ready" href="{{ route('admin.orders.index', ['status' => 'LISTO']) }}"><span>Listos para entregar</span><strong>{{ $readyOrders }}</strong><small>Esperando atención</small></a>
        <a class="stat-card" href="{{ route('admin.products.index') }}"><span>Productos activos</span><strong>{{ $productsCount }}</strong><small>Disponibles en menú</small></a>
        <a class="stat-card" href="{{ route('admin.categories.index') }}"><span>Categorías</span><strong>{{ $categoriesCount }}</strong><small>Organización del menú</small></a>
        <a class="stat-card" href="{{ route('admin.tables.index') }}"><span>Mesas</span><strong>{{ $tablesCount }}</strong><small>Gestiona estado y QR</small></a>
        <a class="stat-card" href="{{ route('admin.users.index') }}"><span>Usuarios</span><strong>{{ $usersCount }}</strong><small>Personal con acceso</small></a>
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
                    <tr><td><strong>#{{ $order->id }}</strong><small>{{ $order->created_at?->format('d/m/Y H:i') }}</small></td><td><span class="type-badge">{{ $isTakeaway ? '🥡 Para llevar' : '🪑 Mesa '.($order->tableSession?->restaurantTable?->number ?? '—') }}</span></td><td><span class="status status-order">{{ $order->status->value }}</span></td><td><strong>${{ number_format($order->total, 0, ',', '.') }}</strong></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.orders.show', $order) }}">Ver detalle</a></td></tr>
                @empty
                    <tr><td colspan="5" class="empty-state"><h3>Aún no hay pedidos</h3><p>Los pedidos nuevos aparecerán aquí.</p><a class="button button-primary" href="{{ route('admin.orders.create') }}">Crear primer pedido</a></td></tr>
                @endforelse
            </tbody>
        </table></div>
    </section>
</div>
<style>
.dashboard-focus{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:-8px 0 20px;padding:13px 15px;background:#fff;border:1px solid #e5e5e2;border-radius:12px}.dashboard-focus>div{display:flex;align-items:center;gap:10px}.focus-dot{width:9px;height:9px;border-radius:50%;background:#2e8b57;box-shadow:0 0 0 4px #edf7f0}.dashboard-focus strong,.dashboard-focus small{display:block}.dashboard-focus strong{font-size:.84rem}.dashboard-focus small{margin-top:1px;color:#777;font-size:.74rem}.dashboard-focus>a{color:#555;text-decoration:none;font-size:.78rem;font-weight:750}.dashboard-focus>a:hover{text-decoration:underline}.stat-card{min-height:126px}.stat-card small{display:block;margin-top:8px;color:#8a8a8a;font-size:.72rem}.stat-attention{border-color:#e8dcc0}.stat-ready{border-color:#cfe5d5}.type-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:8px;background:#f5f5f3;font-size:.78rem;font-weight:700}@media(max-width:600px){.dashboard-focus{align-items:flex-start;flex-direction:column}.dashboard-focus>a{padding-left:19px}}
</style>
@endsection

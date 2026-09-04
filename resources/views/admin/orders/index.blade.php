@extends('layouts.app')
@section('title', 'Pedidos | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Operación</span><h2>Pedidos</h2><p>Consulta y gestiona todos los pedidos del restaurante.</p></div><a class="button button-primary" href="{{ route('admin.orders.create') }}">+ Nuevo pedido</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header">
            <div><h3>Pedidos registrados</h3><span id="orders-count">{{ $orders->count() }} mostrados</span></div>
            <div class="orders-toolbar">
                <input id="order-search" class="admin-search" type="search" placeholder="Buscar # o mesa..." aria-label="Buscar pedidos">
                <form method="GET" action="{{ route('admin.orders.index') }}" class="filter-form"><select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" {{ $selectedStatus === $status->value ? 'selected' : '' }}>{{ $status->value }}</option>@endforeach</select><button class="button button-small" type="submit">Filtrar</button>@if($selectedStatus)<a class="button button-small" href="{{ route('admin.orders.index') }}">Limpiar</a>@endif</form>
            </div>
        </div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Pedido</th><th>Tipo</th><th>Atención</th><th>Productos</th><th>Total</th><th>Estado</th><th class="actions-cell">Acciones</th></tr></thead>
            <tbody id="orders-body">
            @forelse ($orders as $order)
                @php $isTakeaway = $order->type?->value === 'PARA_LLEVAR'; $statusClass = strtolower($order->status->value); @endphp
                <tr data-search="{{ strtolower('#'.$order->id.' '.($isTakeaway?'para llevar':'mesa '.($order->tableSession?->restaurantTable?->number ?? ''))) }}">
                    <td><strong>#{{ $order->id }}</strong><small>{{ $order->created_at?->format('d/m/Y H:i') }}</small></td>
                    <td><span class="type-badge">{{ $isTakeaway ? '🥡 Para llevar' : '🪑 Mesa' }}</span></td>
                    <td>{{ $isTakeaway ? '—' : 'Mesa '.($order->tableSession?->restaurantTable?->number ?? '—') }}</td>
                    <td>{{ $order->orderItems->sum('quantity') }} {{ $order->orderItems->sum('quantity') === 1 ? 'unidad' : 'unidades' }}</td>
                    <td><strong>${{ number_format($order->total, 0, ',', '.') }}</strong></td>
                    <td><span class="status status-order status-{{ $statusClass }}">{{ $order->status->value }}</span></td>
                    <td class="actions-cell"><a class="button button-small" href="{{ route('admin.orders.show', $order) }}">Ver detalle</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state"><h3>No hay pedidos para mostrar</h3><p>Prueba otro filtro o crea un pedido nuevo.</p><a class="button button-primary" href="{{ route('admin.orders.create') }}">Crear pedido</a></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div id="orders-empty" class="empty-state" hidden><h3>Sin resultados</h3><p>No encontramos pedidos con esa búsqueda.</p></div>
    </section>
</div>
<style>
.orders-toolbar{display:flex;align-items:center;gap:8px}.admin-search{min-height:36px;width:190px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.data-table tr[data-search][hidden]{display:none}.type-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:8px;background:#f5f5f3;font-size:.78rem;font-weight:700}.status-pendiente{background:#fff7df;color:#7b5b00}.status-preparando{background:#eef2ff;color:#3447a6}.status-listo{background:#e8f7ed;color:#176b38}.status-entregado{background:#f0f1f2;color:#4a4d50}.status-cancelado{background:#fff0f0;color:#9b2929}@media(max-width:900px){.orders-toolbar{align-items:stretch;flex-direction:column}.admin-search{width:100%}.orders-toolbar .filter-form{width:100%}.orders-toolbar .filter-form select{flex:1}}@media(max-width:560px){.orders-toolbar .filter-form{display:grid;grid-template-columns:1fr 1fr}.orders-toolbar .filter-form select{grid-column:1/-1}}
</style>
<script>
const orderSearch=document.getElementById('order-search'),ordersCount=document.getElementById('orders-count'),ordersEmpty=document.getElementById('orders-empty');
function filterOrders(){const q=(orderSearch?.value||'').trim().toLowerCase();let shown=0;document.querySelectorAll('#orders-body tr[data-search]').forEach(row=>{const show=!q||row.dataset.search.includes(q);row.hidden=!show;if(show)shown++;});if(ordersCount)ordersCount.textContent=`${shown} ${shown===1?'pedido':'pedidos'} mostrados`;if(ordersEmpty)ordersEmpty.hidden=shown!==0;}
orderSearch?.addEventListener('input',filterOrders);
</script>
@endsection

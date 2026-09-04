@extends('layouts.app')
@section('title', 'Pedidos | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Operación</span><h2>Pedidos</h2><p>Consulta y gestiona todos los pedidos del restaurante.</p></div><a class="button button-primary" href="{{ route('admin.orders.create') }}">+ Nuevo pedido</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header">
            <div><h3>Pedidos registrados</h3><span>{{ $orders->count() }} mostrados</span></div>
            <form method="GET" action="{{ route('admin.orders.index') }}" class="filter-form"><select name="status" aria-label="Filtrar por estado"><option value="">Todos los estados</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" {{ $selectedStatus === $status->value ? 'selected' : '' }}>{{ $status->value }}</option>@endforeach</select><button class="button button-small" type="submit">Filtrar</button>@if($selectedStatus)<a class="button button-small" href="{{ route('admin.orders.index') }}">Limpiar</a>@endif</form>
        </div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Pedido</th><th>Tipo</th><th>Atención</th><th>Productos</th><th>Total</th><th>Estado</th><th class="actions-cell">Acciones</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                @php $isTakeaway = $order->type?->value === 'PARA_LLEVAR'; @endphp
                <tr>
                    <td><strong>#{{ $order->id }}</strong><small>{{ $order->created_at?->format('d/m/Y H:i') }}</small></td>
                    <td><span class="type-badge">{{ $isTakeaway ? '🥡 Para llevar' : '🪑 Mesa' }}</span></td>
                    <td>{{ $isTakeaway ? '—' : 'Mesa '.($order->tableSession?->restaurantTable?->number ?? '—') }}</td>
                    <td>{{ $order->orderItems->sum('quantity') }} {{ $order->orderItems->sum('quantity') === 1 ? 'unidad' : 'unidades' }}</td>
                    <td><strong>${{ number_format($order->total, 0, ',', '.') }}</strong></td>
                    <td><span class="status status-order">{{ $order->status->value }}</span></td>
                    <td class="actions-cell"><a class="button button-small" href="{{ route('admin.orders.show', $order) }}">Ver detalle</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state"><h3>No hay pedidos para mostrar</h3><p>Prueba otro filtro o crea un pedido nuevo.</p><a class="button button-primary" href="{{ route('admin.orders.create') }}">Crear pedido</a></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
<style>.type-badge{display:inline-flex;align-items:center;gap:5px;padding:5px 8px;border-radius:8px;background:#f5f5f3;font-size:.78rem;font-weight:700}</style>
@endsection

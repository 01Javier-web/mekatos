@extends('layouts.app')
@section('title', 'Pedidos | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Operación</span><h2>Pedidos</h2><p>Consulta y gestiona los pedidos del restaurante.</p></div><a class="button button-primary" href="{{ route('admin.orders.create') }}">+ Nuevo pedido</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header">
            <div><h3>Pedidos registrados</h3><span>{{ $orders->count() }} mostrados</span></div>
            <form method="GET" action="{{ route('admin.orders.index') }}" class="filter-form"><select name="status"><option value="">Todos los estados</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" {{ $selectedStatus === $status->value ? 'selected' : '' }}>{{ $status->value }}</option>@endforeach</select><button class="button button-small" type="submit">Filtrar</button></form>
        </div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Pedido</th><th>Tipo</th><th>Mesa</th><th>Productos</th><th>Total</th><th>Estado</th><th class="actions-cell">Acciones</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td><strong>#{{ $order->id }}</strong><small>{{ $order->created_at?->format('d/m/Y H:i') }}</small></td>
                    <td>{{ $order->type?->value ?? 'MESA' }}</td>
                    <td>{{ $order->tableSession?->restaurantTable ? 'Mesa '.$order->tableSession->restaurantTable->number : '—' }}</td>
                    <td>{{ $order->orderItems->sum('quantity') }} unidades</td>
                    <td>${{ number_format($order->total, 0, ',', '.') }}</td>
                    <td><span class="status status-order">{{ $order->status->value }}</span></td>
                    <td class="actions-cell"><a class="button button-small" href="{{ route('admin.orders.show', $order) }}">Ver detalle</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty-state">No hay pedidos para mostrar.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Reporte diario | Mekatos')
@section('content')
<div class="page-shell report-page">
    <div class="page-heading">
        <div><span class="eyebrow">Administración</span><h2>Reporte diario de ventas</h2><p>Resumen completo de la operación y las ventas pagadas del día seleccionado.</p></div>
        <form class="report-date" method="GET" action="{{ route('admin.reports.daily') }}"><label for="report-date">Fecha</label><input id="report-date" type="date" name="date" value="{{ $date }}"><button class="button button-primary" type="submit">Consultar</button></form>
    </div>

    <section class="stats-grid report-stats">
        <div class="stat-card stat-revenue"><span>Ventas del día</span><strong>${{ number_format($summary['revenue'],0,',','.') }}</strong><small>Pedidos pagados</small></div>
        <div class="stat-card"><span>Pedidos cobrados</span><strong>{{ number_format($summary['orders']) }}</strong><small>Completados y pagados</small></div>
        <div class="stat-card"><span>Productos vendidos</span><strong>{{ number_format($summary['items']) }}</strong><small>Unidades</small></div>
        <div class="stat-card"><span>Ticket promedio</span><strong>${{ number_format($summary['average_ticket'],0,',','.') }}</strong><small>Venta promedio</small></div>
        <div class="stat-card"><span>Pedidos en mesa</span><strong>{{ $summary['table_orders'] }}</strong><small>{{ $summary['tables_served'] }} mesas atendidas</small></div>
        <div class="stat-card"><span>Para llevar</span><strong>{{ $summary['takeaway_orders'] }}</strong><small>Pedidos cobrados</small></div>
        <div class="stat-card"><span>Meseros activos</span><strong>{{ $summary['unique_waiters'] }}</strong><small>Con ventas registradas</small></div>
    </section>

    <div class="report-columns">
        <section class="panel"><div class="panel-header"><div><h3>🏆 Meseros que más atendieron</h3><span>Ordenado por cantidad de pedidos cobrados.</span></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Mesero</th><th>Pedidos</th><th>Ventas</th></tr></thead><tbody>@forelse($waiterSales as $row)<tr><td><strong>{{ $row->name }}</strong></td><td>{{ $row->orders_count }}</td><td><strong>${{ number_format($row->revenue,0,',','.') }}</strong></td></tr>@empty<tr><td colspan="3">No hay ventas asociadas a meseros.</td></tr>@endforelse</tbody></table></div></section>
        <section class="panel"><div class="panel-header"><div><h3>📦 Productos más vendidos</h3><span>Unidades y dinero generado.</span></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Producto</th><th>Cant.</th><th>Ventas</th></tr></thead><tbody>@forelse($productSales as $row)<tr><td><strong>{{ $row->name }}</strong><small>{{ $row->category ?? 'Sin categoría' }}</small></td><td>{{ $row->quantity }}</td><td><strong>${{ number_format($row->revenue,0,',','.') }}</strong></td></tr>@empty<tr><td colspan="3">No hay productos vendidos.</td></tr>@endforelse</tbody></table></div></section>
    </div>

    <div class="report-columns">
        <section class="panel"><div class="panel-header"><div><h3>💰 Ventas por categoría</h3><span>Qué áreas del menú generan más ingresos.</span></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Categoría</th><th>Unidades</th><th>Ventas</th></tr></thead><tbody>@forelse($categorySales as $row)<tr><td><strong>{{ $row->category ?? 'Sin categoría' }}</strong></td><td>{{ $row->quantity }}</td><td><strong>${{ number_format($row->revenue,0,',','.') }}</strong></td></tr>@empty<tr><td colspan="3">No hay ventas por categoría.</td></tr>@endforelse</tbody></table></div></section>
        <section class="panel"><div class="panel-header"><div><h3>🚚 Entregas por usuario</h3><span>Quién realizó las entregas de pedidos cobrados.</span></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Usuario</th><th>Entregas</th></tr></thead><tbody>@forelse($deliveryStats as $row)<tr><td><strong>{{ $row->name }}</strong></td><td>{{ $row->delivered_count }}</td></tr>@empty<tr><td colspan="2">No hay entregas registradas.</td></tr>@endforelse</tbody></table></div></section>
    </div>

    <section class="panel"><div class="panel-header"><div><h3>🕐 Ventas por hora</h3><span>Momento del día en que se cobraron los pedidos.</span></div></div><div class="hour-grid">@forelse($hourlySales as $row)<div class="hour-card"><span>{{ str_pad($row->hour,2,'0',STR_PAD_LEFT) }}:00</span><strong>${{ number_format($row->revenue,0,',','.') }}</strong><small>{{ $row->orders_count }} pedidos</small></div>@empty<p class="empty-copy">No hay ventas para mostrar.</p>@endforelse</div></section>

    <section class="panel"><div class="panel-header"><div><h3>📋 Estado de los pedidos creados</h3><span>Todos los pedidos registrados durante la fecha seleccionada.</span></div></div><div class="status-list">@forelse($statusCounts as $row)<div><span>{{ $row->status }}</span><strong>{{ $row->total }}</strong></div>@empty<p class="empty-copy">No hay pedidos creados ese día.</p>@endforelse</div></section>

    <section class="panel"><div class="panel-header"><div><h3>🧾 Detalle de ventas</h3><span>Todos los pedidos cobrados, con responsables y hora de pago.</span></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Pedido</th><th>Atención</th><th>Responsable</th><th>Pago</th><th>Total</th></tr></thead><tbody>@forelse($paymentTimeline as $order)<tr><td><strong>#{{ $order->id }}</strong><small>{{ $order->type?->value }}</small></td><td>{{ $order->tableSession?->restaurantTable?->number ? 'Mesa '.$order->tableSession->restaurantTable->number : 'Para llevar' }}</td><td>{{ $order->handledBy?->name ?? '—' }}</td><td>{{ $order->paid_at?->format('H:i') ?? '—' }}<small>{{ $order->paidBy?->name ?? '—' }}</small></td><td><strong>${{ number_format($order->total,0,',','.') }}</strong></td></tr>@empty<tr><td colspan="5" class="empty-state">No hubo ventas cobradas este día.</td></tr>@endforelse</tbody></table></div></section>

    <div class="report-note"><strong>Importante:</strong> las ventas se contabilizan por la fecha y hora de <code>paid_at</code>, es decir, cuando el pedido fue registrado como pagado. Así el reporte representa dinero efectivamente cobrado.</div>
</div>
<style>
.report-date{display:flex;align-items:end;gap:8px}.report-date label{display:block;font-size:.72rem;font-weight:750;color:#666}.report-date input{min-height:40px;padding:8px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.stat-revenue{border-color:#cfe5d5}.report-columns{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:18px}.report-page .panel{margin-top:18px}.report-page .report-columns .panel{margin-top:0}.data-table td small{display:block;color:#888;margin-top:3px;font-size:.72rem}.hour-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(135px,1fr));gap:10px}.hour-card{padding:13px;border:1px solid #e8e8e5;border-radius:10px;background:#fafaf8}.hour-card span,.hour-card strong,.hour-card small{display:block}.hour-card span{font-size:.72rem;color:#777}.hour-card strong{font-size:1rem;margin:4px 0}.hour-card small{font-size:.7rem;color:#888}.status-list{display:flex;gap:10px;flex-wrap:wrap}.status-list>div{min-width:150px;padding:12px 14px;border:1px solid #e5e5e2;border-radius:9px;background:#fafaf8}.status-list span{display:block;font-size:.74rem;color:#777}.status-list strong{font-size:1.25rem}.report-note{margin-top:18px;padding:13px 15px;background:#f5f5f2;border:1px solid #e3e3df;border-radius:10px;color:#666;font-size:.78rem}.report-note code{font-size:.75rem}@media(max-width:850px){.report-columns{grid-template-columns:1fr}.report-date{align-items:stretch;flex-wrap:wrap}.report-date input{flex:1}}@media(max-width:560px){.report-date{width:100%}.report-date input{width:100%}.report-date .button{width:100%}}
</style>
@endsection

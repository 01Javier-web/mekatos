@extends('layouts.app')
@section('title', 'Panel de meseros | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div><span class="eyebrow">Operación</span><h2>Panel de meseros</h2><p>Gestiona los pedidos y mantén el servicio en movimiento.</p></div>
        <div class="waiter-actions"><a class="button button-primary" href="{{ route('admin.orders.create') }}">+ Nuevo pedido</a><a class="button" href="{{ route('waiter.orders') }}" title="Recargar pedidos">↻ Actualizar</a></div>
    </div>
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <section class="stats-grid" aria-label="Resumen de pedidos">
        <div class="stat-card"><span>Pedidos activos</span><strong>{{ $counts['total'] }}</strong><small>En seguimiento</small></div>
        <div class="stat-card stat-attention"><span>Pendientes</span><strong>{{ $counts['pending'] }}</strong><small>Esperando preparación</small></div>
        <div class="stat-card"><span>En preparación</span><strong>{{ $counts['preparing'] }}</strong><small>En cocina</small></div>
        <div class="stat-card stat-ready"><span>Listos</span><strong>{{ $counts['ready'] }}</strong><small>Para entregar</small></div>
    </section>

    <section class="waiter-toolbar panel" aria-label="Filtros de pedidos">
        <div><strong>Pedidos activos</strong><span id="visible-count">{{ $counts['total'] }} {{ $counts['total'] === 1 ? 'pedido' : 'pedidos' }}</span></div>
        <div class="waiter-filters" role="group" aria-label="Filtrar pedidos"><button class="filter-pill is-active" type="button" data-filter="all">Todos</button><button class="filter-pill" type="button" data-filter="table">Mesas</button><button class="filter-pill" type="button" data-filter="takeaway">Para llevar</button><input id="waiter-search" type="search" placeholder="Buscar # o mesa..." aria-label="Buscar pedidos"></div>
    </section>

    <div class="waiter-grid" id="waiter-grid">
        @forelse ($orders as $order)
            @php $status = $order->status; $isTakeaway = $order->type?->value === 'PARA_LLEVAR'; $tableNumber = $order->tableSession?->restaurantTable?->number; @endphp
            <article class="waiter-card" data-kind="{{ $isTakeaway ? 'takeaway' : 'table' }}" data-search="{{ strtolower('#'.$order->id.' '.($tableNumber ? 'mesa '.$tableNumber : 'para llevar')) }}">
                <div class="waiter-card-top"><div><span class="eyebrow">Pedido #{{ $order->id }}</span><h3>{{ $isTakeaway ? 'Para llevar' : 'Mesa '.$tableNumber }}</h3></div><span class="status status-order status-{{ strtolower($status->value) }}">{{ $status->value }}</span></div>
                <div class="waiter-meta"><div><span>Tipo</span><strong>{{ $isTakeaway ? '🥡 Para llevar' : '🪑 Servicio en mesa' }}</strong></div><div><span>Hora</span><strong>{{ $order->created_at?->format('H:i') ?? '—' }}</strong></div></div>
                @if ($order->notes)<div class="info-box"><strong>Nota para cocina</strong><br>{{ $order->notes }}</div>@endif
                <div class="waiter-items">@foreach ($order->orderItems as $item)<div><span><strong>{{ $item->quantity }}×</strong> {{ $item->product?->name ?? 'Producto' }}</span><strong>${{ number_format($item->total, 0, ',', '.') }}</strong></div>@endforeach</div>
                <div class="waiter-total"><span>Total</span><strong>${{ number_format($order->total, 0, ',', '.') }}</strong></div>
                <div class="waiter-card-actions">
                    @if ($status === \App\Enums\OrderStatus::PENDING)
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PUT')<input type="hidden" name="status" value="PREPARANDO"><button class="button button-primary" type="submit">Iniciar preparación</button></form>
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PUT')<input type="hidden" name="status" value="CANCELADO"><button class="button button-danger" type="submit">Cancelar</button></form>
                    @elseif ($status === \App\Enums\OrderStatus::PREPARING)
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PUT')<input type="hidden" name="status" value="LISTO"><button class="button button-primary" type="submit">Marcar como listo</button></form>
                        <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PUT')<input type="hidden" name="status" value="CANCELADO"><button class="button button-danger" type="submit">Cancelar</button></form>
                    @elseif ($status === \App\Enums\OrderStatus::READY)
                        <form method="POST" action="{{ route('admin.orders.deliver', $order) }}">@csrf @method('PUT')<button class="button button-primary" type="submit">Entregar pedido</button></form>
                    @endif
                </div>
            </article>
        @empty
            <div class="panel empty-state" style="grid-column:1/-1;"><h3>No hay pedidos activos</h3><p>Cuando entre un nuevo pedido aparecerá aquí.</p><a class="button button-primary" href="{{ route('admin.orders.create') }}">Crear pedido</a></div>
        @endforelse
    </div>
    <div id="waiter-empty-filter" class="panel empty-state" hidden><h3>No hay pedidos con ese filtro</h3><p>Prueba con otra búsqueda o muestra todos los pedidos.</p></div>
</div>
<style>
.waiter-actions{display:flex;gap:8px;flex-wrap:wrap}.waiter-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:13px 16px;margin-bottom:16px}.waiter-toolbar>div:first-child span{margin-left:8px;color:#888;font-size:.78rem}.waiter-filters{display:flex;align-items:center;gap:6px;flex-wrap:wrap}.filter-pill{border:1px solid #ddd;background:#fff;color:#666;border-radius:999px;padding:7px 11px;font-size:.75rem;font-weight:750}.filter-pill:hover,.filter-pill.is-active{background:#171717;color:#fff;border-color:#171717}.waiter-filters input{min-height:34px;width:190px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.waiter-card{transition:box-shadow .15s ease,transform .15s ease}.waiter-card:hover{transform:translateY(-2px)}.waiter-meta{display:grid;grid-template-columns:1fr auto;gap:16px;margin:16px 0;padding:12px 0;border-top:1px solid #eee;border-bottom:1px solid #eee}.waiter-meta span{display:block;color:#888;font-size:.72rem;margin-bottom:2px}.waiter-meta strong{font-size:.82rem}.waiter-card-actions{display:grid;grid-template-columns:1fr auto;gap:8px}.waiter-card-actions form:first-child .button{width:100%}.waiter-card-actions form:not(:first-child) .button{width:100%}.waiter-card[data-hidden="true"]{display:none}.status-pendiente{background:#fff7df;color:#7b5b00}.status-preparando{background:#eef2ff;color:#3447a6}.status-listo{background:#e8f7ed;color:#176b38}@media(max-width:850px){.waiter-toolbar{align-items:flex-start;flex-direction:column}.waiter-filters input{width:100%}}@media(max-width:560px){.waiter-card-actions{grid-template-columns:1fr}.waiter-meta{grid-template-columns:1fr 1fr}}
</style>
<script>
const waiterGrid=document.getElementById('waiter-grid'), waiterSearch=document.getElementById('waiter-search'), visibleCount=document.getElementById('visible-count'), filterEmpty=document.getElementById('waiter-empty-filter');let activeFilter='all';
function applyWaiterFilter(){const q=(waiterSearch?.value||'').trim().toLowerCase();let shown=0;document.querySelectorAll('.waiter-card[data-kind]').forEach(card=>{const kindOk=activeFilter==='all'||card.dataset.kind===activeFilter;const searchOk=!q||card.dataset.search.includes(q);const show=kindOk&&searchOk;card.dataset.hidden=String(!show);if(show)shown++;});if(visibleCount)visibleCount.textContent=`${shown} ${shown===1?'pedido':'pedidos'}`;if(filterEmpty)filterEmpty.hidden=shown!==0;}
document.querySelectorAll('.filter-pill').forEach(btn=>btn.addEventListener('click',()=>{activeFilter=btn.dataset.filter;document.querySelectorAll('.filter-pill').forEach(x=>x.classList.toggle('is-active',x===btn));applyWaiterFilter();}));waiterSearch?.addEventListener('input',applyWaiterFilter);applyWaiterFilter();
</script>
@endsection

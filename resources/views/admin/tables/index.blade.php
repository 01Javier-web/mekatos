@extends('layouts.app')
@section('title', 'Mesas | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Mesas</h2><p>Gestiona mesas, estados y accesos del menú por QR.</p></div><a class="button button-primary" href="{{ route('admin.tables.create') }}">+ Nueva mesa</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header"><div><h3>Mesas registradas</h3><span>{{ $tables->count() }} mesas</span></div><input id="table-search" class="admin-search" type="search" placeholder="Buscar mesa..." aria-label="Buscar mesa"></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Mesa</th><th>Nombre</th><th>Capacidad</th><th>Estado</th><th>Acceso QR</th><th class="actions-cell">Acciones</th></tr></thead><tbody id="table-rows">
        @forelse ($tables as $table)
            @php $qrUrl=url('/mesa/'.$table->qr_token); @endphp
            <tr data-search="{{ strtolower($table->number.' '.($table->name??'').' '.$table->status->value) }}"><td><strong>#{{ $table->number }}</strong></td><td>{{ $table->name ?: 'Sin nombre' }}</td><td>{{ $table->capacity ?? '—' }} {{ $table->capacity ? 'personas' : '' }}</td><td><span class="status {{ $table->status->value==='AVAILABLE'?'status-active':'status-inactive' }}">{{ $table->status->value }}</span></td><td><div class="qr-actions"><a href="{{ $qrUrl }}" target="_blank" rel="noopener">Abrir menú</a><button type="button" class="button button-small" data-copy="{{ $qrUrl }}">Copiar enlace</button></div><small>Token: {{ $table->qr_token }}</small></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.tables.edit',$table) }}">Editar</a><form method="POST" action="{{ route('admin.tables.destroy',$table) }}" class="inline-form" onsubmit="return confirm('¿Eliminar esta mesa? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form></td></tr>
        @empty<tr><td colspan="6" class="empty-state"><h3>No hay mesas</h3><p>Crea las mesas del restaurante para habilitar los accesos QR.</p><a class="button button-primary" href="{{ route('admin.tables.create') }}">Nueva mesa</a></td></tr>@endforelse
        </tbody></table></div>
        <div id="tables-empty" class="empty-state" hidden><h3>Sin resultados</h3><p>No encontramos mesas con esa búsqueda.</p></div>
    </section>
</div>
<style>.admin-search{min-height:36px;width:220px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.data-table tr[hidden]{display:none}.qr-actions{display:flex;align-items:center;gap:7px}.qr-actions a{font-weight:750}.qr-actions .button{font-size:.72rem}@media(max-width:760px){.admin-search{width:100%}.qr-actions{align-items:flex-start;flex-direction:column}}</style>
<script>const ts=document.getElementById('table-search'),te=document.getElementById('tables-empty');ts?.addEventListener('input',()=>{const q=ts.value.trim().toLowerCase();let shown=0;document.querySelectorAll('#table-rows tr[data-search]').forEach(r=>{const ok=!q||r.dataset.search.includes(q);r.hidden=!ok;if(ok)shown++});te.hidden=shown!==0||document.querySelectorAll('#table-rows tr[data-search]').length===0});document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copy);const old=btn.textContent;btn.textContent='¡Copiado!';setTimeout(()=>btn.textContent=old,1400)}catch{window.prompt('Copia este enlace:',btn.dataset.copy)}}));</script>
@endsection

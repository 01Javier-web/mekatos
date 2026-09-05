@extends('layouts.app')
@section('title', 'Mesas | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Mesas</h2><p>Gestiona mesas, estados y accesos del menú por QR.</p></div><a class="button button-primary" href="{{ route('admin.tables.create') }}">+ Nueva mesa</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header order-filter-header"><div><h3>Mesas registradas</h3><span id="tables-count">{{ $tables->count() }} mesas</span></div><div class="table-tools"><input id="table-search" class="admin-search" type="search" placeholder="Buscar mesa..." aria-label="Buscar mesa"><select id="table-status" aria-label="Filtrar estado"><option value="">Todos los estados</option><option value="AVAILABLE">Disponibles</option><option value="OCCUPIED">Ocupadas</option><option value="CLEANING">En limpieza</option></select></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Mesa</th><th>Nombre</th><th>Capacidad</th><th>Estado</th><th>Acceso QR</th><th class="actions-cell">Acciones</th></tr></thead><tbody id="table-rows">
        @forelse ($tables as $table)
            @php $qrUrl=url('/mesa/'.$table->qr_token); @endphp
            <tr data-search="{{ strtolower($table->number.' '.($table->name??'').' '.$table->status->value) }}" data-status="{{ $table->status->value }}"><td><strong>#{{ $table->number }}</strong></td><td>{{ $table->name ?: 'Sin nombre' }}</td><td>{{ $table->capacity ?? '—' }} {{ $table->capacity ? 'personas' : '' }}</td><td><span class="status {{ $table->status->value==='AVAILABLE'?'status-active':'status-inactive' }}">{{ match($table->status->value){'AVAILABLE'=>'Disponible','OCCUPIED'=>'Ocupada','CLEANING'=>'Limpieza',default=>$table->status->value} }}</span></td><td><div class="qr-actions"><button type="button" class="button button-small" data-qr-url="{{ $qrUrl }}" data-qr-table="{{ $table->number }}" data-qr-name="{{ $table->name ?: 'Mesa '.$table->number }}">Ver QR</button><a class="button button-small" href="{{ $qrUrl }}" target="_blank" rel="noopener">Abrir menú</a><button type="button" class="button button-small" data-copy="{{ $qrUrl }}">Copiar enlace</button></div><small>QR exclusivo de esta mesa</small></td><td class="actions-cell"><a class="button button-small" href="{{ route('admin.tables.edit',$table) }}">Editar</a><form method="POST" action="{{ route('admin.tables.destroy',$table) }}" class="inline-form" onsubmit="return confirm('¿Eliminar esta mesa? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form></td></tr>
        @empty<tr><td colspan="6" class="empty-state"><h3>No hay mesas</h3><p>Crea las mesas del restaurante para habilitar los accesos QR.</p><a class="button button-primary" href="{{ route('admin.tables.create') }}">Nueva mesa</a></td></tr>@endforelse
        </tbody></table></div>
        <div id="tables-empty" class="empty-state" hidden><h3>Sin resultados</h3><p>No encontramos mesas con esos filtros.</p></div>
    </section>
</div>

<div id="qr-modal" class="qr-modal" hidden aria-hidden="true">
    <div class="qr-backdrop" data-qr-close></div>
    <section class="qr-dialog" role="dialog" aria-modal="true" aria-labelledby="qr-title">
        <button type="button" class="qr-close" data-qr-close aria-label="Cerrar">&times;</button>
        <span class="eyebrow">Acceso del cliente</span>
        <h3 id="qr-title">QR de la mesa</h3>
        <p id="qr-subtitle">Escanea este código para abrir el menú.</p>
        <div id="qr-code" class="qr-code" aria-label="Código QR"></div>
        <div class="qr-url" id="qr-url-text"></div>
        <div class="qr-dialog-actions">
            <button type="button" class="button button-primary" id="qr-print">Imprimir QR</button>
            <button type="button" class="button" data-qr-close>Cerrar</button>
        </div>
    </section>
</div>

<style>
.table-tools{display:flex;gap:8px}.admin-search,.table-tools select{min-height:36px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.admin-search{width:220px}.data-table tr[hidden]{display:none}.qr-actions{display:flex;align-items:center;gap:7px;flex-wrap:wrap}.qr-actions a{font-weight:750}.qr-actions .button{font-size:.72rem}
.qr-modal[hidden]{display:none}.qr-modal{position:fixed;inset:0;z-index:1000;display:grid;place-items:center;padding:20px}.qr-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.58)}.qr-dialog{position:relative;width:min(430px,100%);padding:30px;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.25);text-align:center}.qr-dialog h3{margin:5px 0}.qr-dialog p{margin:0 0 18px;color:#666}.qr-close{position:absolute;right:14px;top:10px;border:0;background:transparent;font-size:30px;line-height:1;cursor:pointer;color:#555}.qr-code{display:grid;place-items:center;min-height:280px;padding:12px;background:#fff}.qr-code img,.qr-code canvas{display:block;width:260px!important;height:260px!important;max-width:100%}.qr-url{margin:12px auto 18px;max-width:350px;padding:9px 12px;border-radius:8px;background:#f4f4f2;color:#666;font-size:.72rem;word-break:break-all}.qr-dialog-actions{display:flex;justify-content:center;gap:8px}.qr-dialog-actions .button{font-size:.8rem}
@media(max-width:760px){.table-tools{width:100%;display:grid;grid-template-columns:1fr 1fr}.admin-search{width:100%;grid-column:1/-1}.qr-actions{align-items:flex-start;flex-direction:column}.qr-dialog{padding:24px 18px}.qr-code{min-height:240px}.qr-code img,.qr-code canvas{width:220px!important;height:220px!important}}
@media print{body>*:not(#qr-modal){display:none!important}.qr-modal{position:static;padding:0}.qr-backdrop,.qr-close,.qr-dialog-actions,.qr-url{display:none!important}.qr-dialog{box-shadow:none;width:100%;padding:30px}.qr-code img,.qr-code canvas{width:360px!important;height:360px!important}}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
const ts=document.getElementById('table-search'),tf=document.getElementById('table-status'),te=document.getElementById('tables-empty'),tc=document.getElementById('tables-count');
function filterTables(){const q=ts.value.trim().toLowerCase(),f=tf.value;let shown=0;document.querySelectorAll('#table-rows tr[data-search]').forEach(r=>{const ok=(!q||r.dataset.search.includes(q))&&(!f||r.dataset.status===f);r.hidden=!ok;if(ok)shown++});tc.textContent=`${shown} ${shown===1?'mesa':'mesas'}${q||f?' encontradas':''}`;te.hidden=shown!==0||document.querySelectorAll('#table-rows tr[data-search]').length===0}
ts?.addEventListener('input',filterTables);tf?.addEventListener('change',filterTables);
document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copy);const old=btn.textContent;btn.textContent='¡Copiado!';setTimeout(()=>btn.textContent=old,1400)}catch{window.prompt('Copia este enlace:',btn.dataset.copy)}}));
const qrModal=document.getElementById('qr-modal'),qrCode=document.getElementById('qr-code'),qrTitle=document.getElementById('qr-title'),qrSubtitle=document.getElementById('qr-subtitle'),qrUrlText=document.getElementById('qr-url-text');let currentQrUrl='';
function openQr(btn){currentQrUrl=btn.dataset.qrUrl;const table=btn.dataset.qrTable,name=btn.dataset.qrName;qrTitle.textContent=`QR — ${name}`;qrSubtitle.textContent=`Escanea este código para abrir el menú de la mesa ${table}.`;qrUrlText.textContent=currentQrUrl;qrCode.innerHTML='';const qr=qrcode(0,'M');qr.addData(currentQrUrl);qr.make();qrCode.innerHTML=qr.createImgTag(8,0);qrModal.hidden=false;qrModal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function closeQr(){qrModal.hidden=true;qrModal.setAttribute('aria-hidden','true');document.body.style.overflow=''}
document.querySelectorAll('[data-qr-url]').forEach(btn=>btn.addEventListener('click',()=>openQr(btn)));document.querySelectorAll('[data-qr-close]').forEach(btn=>btn.addEventListener('click',closeQr));document.getElementById('qr-print')?.addEventListener('click',()=>window.print());document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!qrModal.hidden)closeQr()});
</script>
@endsection

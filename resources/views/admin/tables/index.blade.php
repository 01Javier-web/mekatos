@extends('layouts.app')
@section('title', 'Mesas | Mekatos')
@section('content')
<div class="page-shell tables-page">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Administración</span>
            <h2>Mesas</h2>
            <p>Gestiona las 41 mesas, sus estados y los accesos del menú por QR.</p>
        </div>
        <a class="button button-primary" href="{{ route('admin.tables.create') }}">+ Nueva mesa</a>
    </div>

    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><strong>No se pudo completar la acción.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="panel tables-panel">
        <div class="panel-header tables-panel-header">
            <div>
                <h3>Mesas registradas</h3>
                <span id="tables-count">{{ $tables->count() }} mesas</span>
            </div>
            <div class="table-tools">
                <input id="table-search" class="admin-search" type="search" placeholder="Buscar mesa..." aria-label="Buscar mesa">
                <select id="table-status" aria-label="Filtrar estado">
                    <option value="">Todos los estados</option>
                    <option value="AVAILABLE">Disponibles</option>
                    <option value="OCCUPIED">Ocupadas</option>
                    <option value="CLEANING">En limpieza</option>
                </select>
            </div>
        </div>

        <div class="tables-legend" aria-label="Leyenda de estados">
            <span><i class="legend-dot legend-available"></i> Libre</span>
            <span><i class="legend-dot legend-unavailable"></i> No libre</span>
        </div>

        <div id="table-grid" class="table-grid">
            @forelse ($tables as $table)
                @php
                    $qrUrl = url('/mesa/'.$table->qr_token);
                    $isAvailable = $table->status->value === 'AVAILABLE';
                    $statusLabel = match($table->status->value) {
                        'AVAILABLE' => 'Libre',
                        'OCCUPIED' => 'Ocupada',
                        'CLEANING' => 'En limpieza',
                        default => $table->status->value,
                    };
                @endphp
                <article
                    class="table-card {{ $isAvailable ? 'table-card-available' : 'table-card-unavailable' }}"
                    data-search="{{ strtolower($table->number.' '.($table->name ?? '').' '.$table->status->value) }}"
                    data-status="{{ $table->status->value }}"
                    data-table-number="{{ $table->number }}"
                >
                    <div class="table-card-top">
                        <div class="table-number">{{ $table->number }}</div>
                        <span class="table-status-dot" title="{{ $statusLabel }}" aria-label="{{ $statusLabel }}"></span>
                    </div>
                    <div class="table-card-info">
                        <strong>{{ $table->name ?: 'Mesa '.$table->number }}</strong>
                        <span>{{ $statusLabel }} · {{ $table->capacity ?? '—' }} {{ $table->capacity ? 'pers.' : '' }}</span>
                    </div>
                    <div class="table-card-actions" aria-label="Acciones de mesa {{ $table->number }}">
                        <button type="button" class="table-action" data-qr-url="{{ $qrUrl }}" data-qr-table="{{ $table->number }}" data-qr-name="{{ $table->name ?: 'Mesa '.$table->number }}" title="Ver QR" aria-label="Ver QR de mesa {{ $table->number }}">QR</button>
                        <a class="table-action" href="{{ $qrUrl }}" target="_blank" rel="noopener" title="Abrir menú" aria-label="Abrir menú de mesa {{ $table->number }}">Menú</a>
                        <button type="button" class="table-action" data-copy="{{ $qrUrl }}" title="Copiar enlace" aria-label="Copiar enlace de mesa {{ $table->number }}">Copiar</button>
                        <a class="table-action" href="{{ route('admin.tables.edit',$table) }}" title="Editar mesa" aria-label="Editar mesa {{ $table->number }}">Editar</a>
                        <form method="POST" action="{{ route('admin.tables.destroy',$table) }}" class="table-delete-form" onsubmit="return confirm('¿Eliminar esta mesa? Esta acción no se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button class="table-action table-action-danger" type="submit" title="Eliminar mesa" aria-label="Eliminar mesa {{ $table->number }}">×</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty-state tables-empty-initial">
                    <h3>No hay mesas</h3>
                    <p>Crea las mesas del restaurante para habilitar los accesos QR.</p>
                    <a class="button button-primary" href="{{ route('admin.tables.create') }}">Nueva mesa</a>
                </div>
            @endforelse
        </div>

        <div id="tables-empty" class="empty-state" hidden>
            <h3>Sin resultados</h3>
            <p>No encontramos mesas con esos filtros.</p>
        </div>
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
.tables-panel{overflow:hidden}.tables-panel-header{align-items:center}.table-tools{display:flex;gap:8px}.admin-search,.table-tools select{min-height:36px;padding:7px 10px;border:1px solid #d4d4d1;border-radius:8px;background:#fff}.admin-search{width:210px}.tables-legend{display:flex;align-items:center;gap:18px;margin:-2px 0 14px;color:#666;font-size:.78rem}.tables-legend span{display:inline-flex;align-items:center;gap:7px}.legend-dot{width:10px;height:10px;border-radius:50%;display:inline-block}.legend-available{background:#a9cdb4}.legend-unavailable{background:#d9a4a4}
.table-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px}.table-card{min-width:0;padding:12px 10px 10px;border:1px solid transparent;border-radius:13px;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}.table-card:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(0,0,0,.08)}.table-card-available{background:#edf6ef;border-color:#c9e1cf}.table-card-unavailable{background:#f9eeee;border-color:#ebcccc}.table-card[hidden]{display:none}.table-card-top{display:flex;align-items:center;justify-content:space-between}.table-number{font-size:1.35rem;font-weight:850;line-height:1}.table-status-dot{width:9px;height:9px;border-radius:50%;flex:none}.table-card-available .table-status-dot{background:#7eaf8b}.table-card-unavailable .table-status-dot{background:#c98282}.table-card-info{display:flex;flex-direction:column;gap:2px;margin:7px 0 9px;min-height:30px}.table-card-info strong{font-size:.76rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.table-card-info span{font-size:.67rem;color:#6c6c68;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.table-card-actions{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:4px}.table-action{min-width:0;min-height:27px;padding:4px 3px;border:1px solid rgba(70,70,65,.14);border-radius:6px;background:rgba(255,255,255,.66);color:#4f4f4b;font:inherit;font-size:.61rem;font-weight:750;text-align:center;text-decoration:none;cursor:pointer;line-height:1.1}.table-action:hover{background:#fff}.table-action-danger{color:#a85d5d;border-color:rgba(168,93,93,.22);font-size:.9rem}.table-delete-form{display:contents}.qr-modal[hidden]{display:none}.qr-modal{position:fixed;inset:0;z-index:1000;display:grid;place-items:center;padding:20px}.qr-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.58)}.qr-dialog{position:relative;width:min(430px,100%);padding:30px;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.25);text-align:center}.qr-dialog h3{margin:5px 0}.qr-dialog p{margin:0 0 18px;color:#666}.qr-close{position:absolute;right:14px;top:10px;border:0;background:transparent;font-size:30px;line-height:1;cursor:pointer;color:#555}.qr-code{display:grid;place-items:center;min-height:280px;padding:12px;background:#fff}.qr-code img,.qr-code canvas{display:block;width:260px!important;height:260px!important;max-width:100%}.qr-url{margin:12px auto 18px;max-width:350px;padding:9px 12px;border-radius:8px;background:#f4f4f2;color:#666;font-size:.72rem;word-break:break-all}.qr-dialog-actions{display:flex;justify-content:center;gap:8px}.qr-dialog-actions .button{font-size:.8rem}
@media(max-width:1180px){.table-grid{grid-template-columns:repeat(6,minmax(0,1fr))}}@media(max-width:900px){.table-grid{grid-template-columns:repeat(5,minmax(0,1fr))}}@media(max-width:700px){.tables-panel-header{align-items:flex-start}.table-tools{width:100%;display:grid;grid-template-columns:1fr 1fr}.admin-search{width:100%;grid-column:1/-1}.table-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.table-card{padding:10px 7px 8px}.table-card-actions{grid-template-columns:repeat(3,minmax(0,1fr)) auto}.table-action:nth-child(3){display:none}.table-card-info strong{font-size:.7rem}}@media(max-width:470px){.table-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.table-card-actions{grid-template-columns:repeat(2,minmax(0,1fr)) auto}.table-action:nth-child(3),.table-action:nth-child(4){display:none}.table-card{border-radius:10px}}
@media print{body>*:not(#qr-modal){display:none!important}.qr-modal{position:static;padding:0}.qr-backdrop,.qr-close,.qr-dialog-actions,.qr-url{display:none!important}.qr-dialog{box-shadow:none;width:100%;padding:30px}.qr-code img,.qr-code canvas{width:360px!important;height:360px!important}}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
const ts=document.getElementById('table-search'),tf=document.getElementById('table-status'),te=document.getElementById('tables-empty'),tc=document.getElementById('tables-count');
function filterTables(){const q=ts.value.trim().toLowerCase(),f=tf.value;let shown=0;document.querySelectorAll('#table-grid .table-card').forEach(card=>{const ok=(!q||card.dataset.search.includes(q))&&(!f||card.dataset.status===f);card.hidden=!ok;if(ok)shown++});tc.textContent=`${shown} ${shown===1?'mesa':'mesas'}${q||f?' encontradas':''}`;te.hidden=shown!==0||document.querySelectorAll('#table-grid .table-card').length===0}
ts?.addEventListener('input',filterTables);tf?.addEventListener('change',filterTables);
document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copy);const old=btn.textContent;btn.textContent='¡OK!';setTimeout(()=>btn.textContent=old,1400)}catch{window.prompt('Copia este enlace:',btn.dataset.copy)}}));
const qrModal=document.getElementById('qr-modal'),qrCode=document.getElementById('qr-code'),qrTitle=document.getElementById('qr-title'),qrSubtitle=document.getElementById('qr-subtitle'),qrUrlText=document.getElementById('qr-url-text');let currentQrUrl='';
function openQr(btn){currentQrUrl=btn.dataset.qrUrl;const table=btn.dataset.qrTable,name=btn.dataset.qrName;qrTitle.textContent=`QR — ${name}`;qrSubtitle.textContent=`Escanea este código para abrir el menú de la mesa ${table}.`;qrUrlText.textContent=currentQrUrl;qrCode.innerHTML='';const qr=qrcode(0,'M');qr.addData(currentQrUrl);qr.make();qrCode.innerHTML=qr.createImgTag(8,0);qrModal.hidden=false;qrModal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function closeQr(){qrModal.hidden=true;qrModal.setAttribute('aria-hidden','true');document.body.style.overflow=''}
document.querySelectorAll('[data-qr-url]').forEach(btn=>btn.addEventListener('click',()=>openQr(btn)));document.querySelectorAll('[data-qr-close]').forEach(btn=>btn.addEventListener('click',closeQr));document.getElementById('qr-print')?.addEventListener('click',()=>window.print());document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!qrModal.hidden)closeQr()});
</script>
@endsection

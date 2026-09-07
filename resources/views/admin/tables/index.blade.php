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
                        'RESERVED' => 'Reservada',
                        'OUT_OF_SERVICE' => 'Fuera de servicio',
                        default => $table->status->value,
                    };
                @endphp
                <button
                    type="button"
                    class="table-card {{ $isAvailable ? 'table-card-available' : 'table-card-unavailable' }}"
                    data-search="{{ strtolower($table->number.' '.($table->name ?? '').' '.$table->status->value) }}"
                    data-status="{{ $table->status->value }}"
                    data-table-number="{{ $table->number }}"
                    data-table-name="{{ $table->name ?: 'Mesa '.$table->number }}"
                    data-table-status="{{ $statusLabel }}"
                    data-table-capacity="{{ $table->capacity ?? '' }}"
                    data-qr-url="{{ $qrUrl }}"
                >
                    <span class="table-card-number">Mesa {{ $table->number }}</span>
                    <span class="table-card-status"><i class="table-status-dot"></i>{{ $statusLabel }}</span>
                </button>
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

<div id="table-modal" class="table-modal" hidden aria-hidden="true">
    <div class="table-modal-backdrop" data-table-close></div>
    <section class="table-dialog" role="dialog" aria-modal="true" aria-labelledby="table-dialog-title">
        <button type="button" class="table-dialog-close" data-table-close aria-label="Cerrar">&times;</button>
        <span class="eyebrow">Detalle de mesa</span>
        <div class="table-dialog-heading">
            <div>
                <h3 id="table-dialog-title">Mesa</h3>
                <span id="table-dialog-status" class="table-dialog-status"></span>
            </div>
            <div id="table-dialog-capacity" class="table-dialog-capacity"></div>
        </div>

        <div class="table-detail-grid">
            <a id="table-menu" class="table-detail-action" href="#" target="_blank" rel="noopener">
                <span class="action-icon">↗</span><span><strong>Abrir menú</strong><small>Ver el menú de esta mesa</small></span>
            </a>
            <button id="table-qr" type="button" class="table-detail-action">
                <span class="action-icon">▣</span><span><strong>Ver QR</strong><small>Mostrar código de acceso</small></span>
            </button>
            <button id="table-copy" type="button" class="table-detail-action">
                <span class="action-icon">⧉</span><span><strong>Copiar enlace</strong><small>Copiar acceso al menú</small></span>
            </button>
            <button id="table-print" type="button" class="table-detail-action">
                <span class="action-icon">🖨</span><span><strong>Imprimir QR</strong><small>Imprimir código de acceso</small></span>
            </button>
            <a id="table-edit" class="table-detail-action" href="#">
                <span class="action-icon">✎</span><span><strong>Editar mesa</strong><small>Modificar sus datos</small></span>
            </a>
            <form id="table-delete-form" method="POST" action="#" onsubmit="return confirm('¿Eliminar esta mesa? Esta acción no se puede deshacer.')">
                @csrf @method('DELETE')
                <button type="submit" class="table-detail-action table-detail-danger">
                    <span class="action-icon">⌫</span><span><strong>Eliminar mesa</strong><small>Eliminar definitivamente</small></span>
                </button>
            </form>
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
.table-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:14px}.table-card{appearance:none;width:100%;min-width:0;min-height:105px;padding:16px 12px;border:1px solid transparent;border-radius:15px;box-shadow:0 2px 8px rgba(0,0,0,.05);transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease;background:transparent;font:inherit;text-align:left;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:#333}.table-card:hover{transform:translateY(-2px);box-shadow:0 7px 18px rgba(0,0,0,.09)}.table-card:focus-visible{outline:3px solid rgba(40,90,70,.24);outline-offset:2px}.table-card-available{background:#edf6ef;border-color:#c9e1cf}.table-card-unavailable{background:#f9eeee;border-color:#ebcccc}.table-card[hidden]{display:none}.table-card-number{font-size:1.05rem;font-weight:850;line-height:1.1}.table-card-status{display:flex;align-items:center;gap:7px;font-size:.78rem;color:#666}.table-status-dot{width:9px;height:9px;border-radius:50%;display:inline-block;flex:none}.table-card-available .table-status-dot{background:#7eaf8b}.table-card-unavailable .table-status-dot{background:#c98282}
.table-modal[hidden],.qr-modal[hidden]{display:none}.table-modal,.qr-modal{position:fixed;inset:0;z-index:1000;display:grid;place-items:center;padding:20px}.table-modal-backdrop,.qr-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.56)}.table-dialog{position:relative;width:min(560px,100%);padding:30px;border-radius:20px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.25)}.table-dialog-close{position:absolute;right:14px;top:9px;border:0;background:transparent;font-size:30px;line-height:1;cursor:pointer;color:#555}.table-dialog-heading{display:flex;align-items:center;justify-content:space-between;gap:20px;margin:5px 0 24px;padding-right:25px}.table-dialog-heading h3{margin:0;font-size:1.55rem}.table-dialog-status{display:inline-flex;align-items:center;margin-top:7px;padding:5px 10px;border-radius:999px;background:#edf6ef;color:#52735a;font-size:.76rem;font-weight:750}.table-dialog-capacity{font-size:.8rem;color:#6b6b66}.table-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.table-detail-action{min-height:70px;padding:12px;border:1px solid #deded9;border-radius:11px;background:#fafaf8;color:#333;text-decoration:none;display:flex;align-items:center;gap:12px;text-align:left;cursor:pointer;font:inherit}.table-detail-action:hover{background:#f3f3ef;border-color:#cfcfc8}.table-detail-action .action-icon{width:34px;height:34px;display:grid;place-items:center;flex:none;border-radius:9px;background:#ededE8;font-size:1rem}.table-detail-action strong,.table-detail-action small{display:block}.table-detail-action strong{font-size:.84rem}.table-detail-action small{margin-top:3px;color:#777;font-size:.68rem}.table-detail-danger{width:100%;color:#a35e5e;background:#fcf4f4;border-color:#ead2d2}.table-detail-danger .action-icon{background:#f5e1e1}.table-detail-grid form{margin:0}
.qr-dialog{position:relative;width:min(430px,100%);padding:30px;border-radius:18px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.25);text-align:center}.qr-dialog h3{margin:5px 0}.qr-dialog p{margin:0 0 18px;color:#666}.qr-close{position:absolute;right:14px;top:10px;border:0;background:transparent;font-size:30px;line-height:1;cursor:pointer;color:#555}.qr-code{display:grid;place-items:center;min-height:280px;padding:12px;background:#fff}.qr-code img,.qr-code canvas{display:block;width:260px!important;height:260px!important;max-width:100%}.qr-url{margin:12px auto 18px;max-width:350px;padding:9px 12px;border-radius:8px;background:#f4f4f2;color:#666;font-size:.72rem;word-break:break-all}.qr-dialog-actions{display:flex;justify-content:center;gap:8px}.qr-dialog-actions .button{font-size:.8rem}
@media(max-width:1180px){.table-grid{grid-template-columns:repeat(5,minmax(0,1fr))}}@media(max-width:900px){.table-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:700px){.tables-panel-header{align-items:flex-start}.table-tools{width:100%;display:grid;grid-template-columns:1fr}.admin-search{width:100%}.table-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.table-card{min-height:90px;padding:12px 6px}.table-card-number{font-size:.92rem}.table-card-status{font-size:.68rem}.table-detail-grid{grid-template-columns:1fr}.table-dialog{padding:24px 18px}.table-dialog-heading{margin-bottom:18px}}@media(max-width:470px){.table-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.table-card{min-height:86px}.table-dialog{border-radius:16px;padding:24px 16px}.table-detail-action{min-height:64px}}
@media print{body>*:not(#qr-modal){display:none!important}.qr-modal{position:static;padding:0}.qr-backdrop,.qr-close,.qr-dialog-actions,.qr-url{display:none!important}.qr-dialog{box-shadow:none;width:100%;padding:30px}.qr-code img,.qr-code canvas{width:360px!important;height:360px!important}}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
<script>
const ts=document.getElementById('table-search'),tf=document.getElementById('table-status'),te=document.getElementById('tables-empty'),tc=document.getElementById('tables-count');
function filterTables(){const q=ts.value.trim().toLowerCase(),f=tf.value;let shown=0;document.querySelectorAll('#table-grid .table-card').forEach(card=>{const ok=(!q||card.dataset.search.includes(q))&&(!f||card.dataset.status===f);card.hidden=!ok;if(ok)shown++});tc.textContent=`${shown} ${shown===1?'mesa':'mesas'}${q||f?' encontradas':''}`;te.hidden=shown!==0||document.querySelectorAll('#table-grid .table-card').length===0}
ts?.addEventListener('input',filterTables);tf?.addEventListener('change',filterTables);
const tableModal=document.getElementById('table-modal'),tableTitle=document.getElementById('table-dialog-title'),tableStatus=document.getElementById('table-dialog-status'),tableCapacity=document.getElementById('table-dialog-capacity'),tableMenu=document.getElementById('table-menu'),tableEdit=document.getElementById('table-edit'),tableDelete=document.getElementById('table-delete-form'),tableCopy=document.getElementById('table-copy'),tableQr=document.getElementById('table-qr'),tablePrint=document.getElementById('table-print');let currentTableUrl='';
function openTable(card){const n=card.dataset.tableNumber,name=card.dataset.tableName,status=card.dataset.tableStatus,capacity=card.dataset.tableCapacity;currentTableUrl=card.dataset.qrUrl;tableTitle.textContent=name;tableStatus.textContent=status;tableCapacity.textContent=capacity?`Capacidad: ${capacity} personas`:'';tableMenu.href=currentTableUrl;tableEdit.href=`{{ url('/admin/tables') }}/${n}/edit`;tableDelete.action=`{{ url('/admin/tables') }}/${n}`;tableModal.hidden=false;tableModal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function closeTable(){tableModal.hidden=true;tableModal.setAttribute('aria-hidden','true');document.body.style.overflow=''}
document.querySelectorAll('.table-card').forEach(card=>card.addEventListener('click',()=>openTable(card)));document.querySelectorAll('[data-table-close]').forEach(btn=>btn.addEventListener('click',closeTable));
tableCopy?.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(currentTableUrl);const old=tableCopy.querySelector('strong').textContent;tableCopy.querySelector('strong').textContent='¡Enlace copiado!';setTimeout(()=>tableCopy.querySelector('strong').textContent=old,1400)}catch{window.prompt('Copia este enlace:',currentTableUrl)}});
const qrModal=document.getElementById('qr-modal'),qrCode=document.getElementById('qr-code'),qrTitle=document.getElementById('qr-title'),qrSubtitle=document.getElementById('qr-subtitle'),qrUrlText=document.getElementById('qr-url-text');let currentQrUrl='';
function openQr(url,name,number){currentQrUrl=url;qrTitle.textContent=`QR — ${name}`;qrSubtitle.textContent=`Escanea este código para abrir el menú de la mesa ${number}.`;qrUrlText.textContent=currentQrUrl;qrCode.innerHTML='';const qr=qrcode(0,'M');qr.addData(currentQrUrl);qr.make();qrCode.innerHTML=qr.createImgTag(8,0);qrModal.hidden=false;qrModal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden'}
function closeQr(){qrModal.hidden=true;qrModal.setAttribute('aria-hidden','true');document.body.style.overflow=tableModal.hidden?'':'hidden'}
tableQr?.addEventListener('click',()=>openQr(currentTableUrl,tableTitle.textContent,document.querySelector('.table-card[data-table-number="'+CSS.escape(tableTitle.textContent.replace('Mesa ',''))+'"]')?.dataset.tableNumber||''));tablePrint?.addEventListener('click',()=>{openQr(currentTableUrl,tableTitle.textContent,tableTitle.textContent.replace('Mesa ',''));setTimeout(()=>window.print(),120)});
document.querySelectorAll('[data-qr-close]').forEach(btn=>btn.addEventListener('click',closeQr));document.getElementById('qr-print')?.addEventListener('click',()=>window.print());document.addEventListener('keydown',e=>{if(e.key==='Escape'){if(!qrModal.hidden)closeQr();else if(!tableModal.hidden)closeTable()}});
</script>
@endsection

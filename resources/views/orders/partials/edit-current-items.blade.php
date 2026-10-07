{{-- "✏️ Editar pedido": productos actuales. Quitar anula unidades (nunca borra); Sustituir = quitar
     + elegir el producto nuevo en "+ Agregar producto"; Cambiar salsas = quitar + volver a agregar el
     mismo producto con otras salsas. Lo que ya se envió a cocina sale como "❌ NO PREPARAR". --}}
@php
    $currentItems = $order->orderItems->sortBy('id')->values();
    $mainItems = $currentItems->reject(fn ($item) => $item->product?->is_portion);
@endphp
<section class="panel step" id="current-items"><div class="panel-header"><h3>Productos del pedido</h3><span>{{ $currentItems->sum('quantity') }} {{ $currentItems->sum('quantity') === 1 ? 'unidad' : 'unidades' }}</span></div>
    <div class="current-items">
        @foreach($currentItems as $item)
            @php $sent = $item->sent_at !== null; $canChangeSauces = $saucesEnabled && $item->product?->allows_sauces; @endphp
            <article class="current-item" data-line="{{ $item->id }}" data-qty="{{ $item->quantity }}" data-unit-price="{{ (int) $item->unit_price }}" data-name="{{ $item->product?->name }}" data-product="{{ $item->product_id }}" data-sent="{{ $sent ? '1' : '0' }}" data-packaging="{{ \App\Support\TakeawayPackaging::applies($item->product) ? '1' : '0' }}" data-paired="{{ $item->paired_order_item_id }}">
                <div class="current-item-info">
                    <strong>{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</strong>
                    <span class="current-item-price">${{ number_format($item->total, 0, ',', '.') }}</span>
                    <span class="sent-badge {{ $sent ? 'is-sent' : '' }}">{{ $sent ? 'Enviado a cocina' : 'Sin enviar' }}</span>
                    @if($item->notes)<small>⚠ {{ $item->notes }}</small>@endif
                    @include('orders.partials.item-sauces', ['item' => $item, 'tag' => 'small', 'class' => 'item-sauces'])
                    @if($item->paired_order_item_id)<small>Acompaña a: {{ $item->pairedOrderItem?->product?->name ?? 'Producto' }}</small>@endif
                </div>
                <div class="current-item-actions">
                    <label class="void-control">🗑️ Quitar
                        <span class="qty"><button type="button" data-void-minus="{{ $item->id }}" aria-label="Quitar una unidad menos">−</button><input type="number" name="void[{{ $item->id }}]" value="{{ (int) old('void.'.$item->id, 0) }}" min="0" max="{{ $item->quantity }}" data-void="{{ $item->id }}" readonly><button type="button" data-void-plus="{{ $item->id }}" aria-label="Quitar una unidad más">+</button></span>
                    </label>
                    <button class="button button-small" type="button" data-substitute="{{ $item->id }}">🔄 Sustituir</button>
                    @if($canChangeSauces)<button class="button button-small" type="button" data-change-sauces="{{ $item->id }}">🧂 Cambiar salsas</button>@endif
                </div>
                @if($item->paired_order_item_id)
                    <label class="portion-target" data-portion-target-for="{{ $item->paired_order_item_id }}" hidden>Esta porción acompañará a
                        <select name="portion_target[{{ $item->id }}]" disabled>
                            <option value="">Elige un producto o quita también la porción</option>
                            @foreach($mainItems->where('id', '!=', $item->paired_order_item_id) as $target)
                                <option value="{{ $target->id }}" @selected((int) old('portion_target.'.$item->id) === $target->id)>{{ $target->quantity }} × {{ $target->product?->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            </article>
        @endforeach
    </div>
    @if($order->generalSauces->isNotEmpty())
        <div class="current-general-sauces">
            <strong>Salsas generales (aparte)</strong>
            @foreach($order->generalSauces as $general)
                <label class="general-sauce-remove"><input type="checkbox" name="remove_general_sauces[]" value="{{ $general->id }}" data-remove-general data-sent="{{ $general->sent_at ? '1' : '0' }}" data-name="{{ $general->sauce?->name }}" @checked(in_array($general->id, array_map('intval', (array) old('remove_general_sauces', [])), true))> 🗑️ Quitar {{ $general->sauce?->name }} <span class="sent-badge {{ $general->sent_at ? 'is-sent' : '' }}">{{ $general->sent_at ? 'Enviada a cocina' : 'Sin enviar' }}</span></label>
            @endforeach
        </div>
    @endif
    <p class="edit-hint" id="edit-hint" hidden></p>
</section>
<script>
// Edición: unidades que se quitan de cada línea, sustituciones y cambio de salsas.
function voidInput(id){return document.querySelector(`[data-void="${id}"]`);}
function setVoid(id,value){const i=voidInput(id);if(!i)return;i.value=Math.max(0,Math.min(Number(i.max),value));refresh();}
function editHint(text){const h=document.getElementById('edit-hint');h.textContent=text;h.hidden=!text;}
function goToCatalog(card){(card||document.getElementById('product-grid')).scrollIntoView({behavior:'smooth',block:'start'});}
document.querySelectorAll('[data-void-minus]').forEach(b=>b.onclick=()=>setVoid(b.dataset.voidMinus,Number(voidInput(b.dataset.voidMinus).value)-1));
document.querySelectorAll('[data-void-plus]').forEach(b=>b.onclick=()=>setVoid(b.dataset.voidPlus,Number(voidInput(b.dataset.voidPlus).value)+1));
document.querySelectorAll('[data-substitute]').forEach(b=>b.onclick=()=>{const id=b.dataset.substitute,line=b.closest('.current-item');if(Number(voidInput(id).value)===0)setVoid(id,1);editHint(`🔄 Sustituir ${line.dataset.name}: elige abajo, en "+ Agregar producto", el producto nuevo.`);goToCatalog();});
document.querySelectorAll('[data-remove-general]').forEach(c=>c.addEventListener('change',()=>refresh()));
document.querySelectorAll('[data-change-sauces]').forEach(b=>b.onclick=()=>{const id=b.dataset.changeSauces,line=b.closest('.current-item'),qty=Number(line.dataset.qty);setVoid(id,qty);const input=document.querySelector(`input[data-id="${line.dataset.product}"]`);if(input){input.value=Math.min(99,Number(input.value)+qty);refresh();refreshPortionModes();const picker=input.closest('.card').querySelector('[data-sauce-picker]');if(picker)picker.open=true;goToCatalog(input.closest('.card'));}editHint(`🧂 Cambiar salsas de ${line.dataset.name}: elige las salsas nuevas en el producto marcado abajo.`);});
function editState(){
    const lines=[...document.querySelectorAll('.current-item')].map(line=>({line,id:line.dataset.line,qty:Number(line.dataset.qty),units:Number(voidInput(line.dataset.line).value)||0}));
    const fully=new Set(lines.filter(l=>l.units>0&&l.units===l.qty).map(l=>l.id));
    // Porciones cuyo producto se quita por completo: reasignar o quitarlas también.
    document.querySelectorAll('[data-portion-target-for]').forEach(wrap=>{const own=wrap.closest('.current-item'),need=fully.has(wrap.dataset.portionTargetFor)&&!fully.has(own.dataset.line);wrap.hidden=!need;const s=wrap.querySelector('select');s.disabled=!need;s.required=need;});
    lines.forEach(l=>l.line.classList.toggle('is-voided',l.units>0));
    return lines.filter(l=>l.units>0);
}
function refreshEdit(addTotal){
    const voids=editState(),takeaway=['PARA_LLEVAR','DOMICILIO'].includes('{{ $order->type?->value }}');
    let removed=0;
    const rows=voids.map(v=>{const price=Number(v.line.dataset.unitPrice)*v.units,fee=takeaway&&v.line.dataset.packaging==='1'?1500*v.units:0;removed+=price+fee;return '<div class="summary-line summary-void"><span>❌ Quitar '+v.units+' × '+sauceEsc(v.line.dataset.name)+(v.line.dataset.sent==='1'?'<small>Ya enviado: saldrá como NO PREPARAR</small>':'')+'</span><strong>−'+money(price)+'</strong></div>';});
    const removedGeneral=[...document.querySelectorAll('[data-remove-general]:checked')],addedGeneral=document.querySelectorAll('[data-general-sauces] input[type="checkbox"]:checked').length;
    removedGeneral.forEach(g=>rows.push('<div class="summary-line summary-void"><span>❌ Quitar salsa general '+sauceEsc(g.dataset.name)+(g.dataset.sent==='1'?'<small>Ya enviada: saldrá como NO PREPARAR</small>':'')+'</span><strong>Aparte</strong></div>'));
    const sentVoid=voids.some(v=>v.line.dataset.sent==='1')||removedGeneral.some(g=>g.dataset.sent==='1'),reason=document.getElementById('edit-reason');
    reason.required=sentVoid;document.getElementById('reason-required').hidden=!sentVoid;
    if(rows.length||addedGeneral){
        const base=summary.innerHTML.includes('summary-line')?summary.innerHTML:generalSaucesSummaryHtml(true);
        summary.innerHTML=rows.join('')+base;
    }
    if(voids.length||addTotal>0){summary.insertAdjacentHTML('beforeend','<div class="summary-line summary-total"><strong>Total nuevo (aprox.)</strong><strong>'+money(Math.max(0,{{ (int) $order->total }}-removed+addTotal))+'</strong></div>');}
    submit.disabled=!voids.length&&addTotal<=0&&!removedGeneral.length&&!addedGeneral;
}
</script>

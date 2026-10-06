{{-- Lógica compartida de salsas para "Nuevo pedido" y "Agregar al pedido". --}}
@include('orders.partials.sauce-options-template')
<script>
const SAUCE_PLACEMENT_LABELS={EN_PRODUCTO:'en producto',APARTE:'aparte'};
function sauceEsc(value){return String(value??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));}
function sauceJson(value){try{return JSON.parse(value||'{}')||{};}catch(e){return {};}}
function sauceOptionsHtml(namePrefix){const template=document.getElementById('sauce-options-template');return template?template.innerHTML.replaceAll('__NAME__',namePrefix):'';}
function fillSauceSelects(root,values){root.querySelectorAll('select').forEach(s=>{s.value=(values||{})[s.dataset.sauceId]||'';});}
function describeSauceSelects(root){return [...root.querySelectorAll('select')].filter(s=>!s.disabled&&s.value).map(s=>`${s.closest('.sauce-option')?.querySelector('span')?.textContent||''} (${SAUCE_PLACEMENT_LABELS[s.value]||s.value})`).join(', ');}
function ensureSameSauces(picker){
    const same=picker.querySelector('[data-sauce-same]');
    if(same.dataset.ready)return;
    same.innerHTML=sauceOptionsHtml(`sauces[${picker.dataset.productId}]`);
    fillSauceSelects(same,sauceJson(picker.dataset.oldSame));
    same.dataset.ready='1';
}
function renderSauceUnits(picker,q){
    const units=picker.querySelector('[data-sauce-units]');
    const current=[...units.querySelectorAll('[data-sauce-unit]')];
    if(current.length===q)return;
    // Conserva lo ya elegido en cada unidad; tras un error de validación, restaura lo enviado.
    const saved=current.map(unit=>Object.fromEntries([...unit.querySelectorAll('select')].map(s=>[s.dataset.sauceId,s.value])));
    let old={};
    if(!units.dataset.restored){old=sauceJson(picker.dataset.oldUnits);units.dataset.restored='1';}
    units.innerHTML='';
    for(let i=0;i<q;i++){
        const unit=document.createElement('div');
        unit.className='sauce-unit';
        unit.setAttribute('data-sauce-unit','');
        unit.innerHTML=`<strong>Unidad ${i+1}</strong><div class="sauce-options">${sauceOptionsHtml(`unit_sauces[${picker.dataset.productId}][${i}]`)}</div>`;
        fillSauceSelects(unit,saved[i]||old[i]||old[String(i)]||{});
        units.appendChild(unit);
    }
}
function syncSauces(card,q,enabled){
    const picker=card.querySelector('[data-sauce-picker]');
    if(!picker)return;
    const show=q>0&&enabled;
    picker.hidden=!show;
    if(show)ensureSameSauces(picker);
    const modeWrap=picker.querySelector('[data-sauce-mode-wrap]'),mode=picker.querySelector('[data-sauce-mode]');
    const canChoose=show&&q>1;
    modeWrap.hidden=!canChoose;
    mode.disabled=!canChoose;
    const each=canChoose&&mode.value==='each';
    const same=picker.querySelector('[data-sauce-same]');
    same.hidden=each;
    same.querySelectorAll('select').forEach(s=>s.disabled=!show||each);
    const units=picker.querySelector('[data-sauce-units]');
    if(each){renderSauceUnits(picker,q);}else{units.innerHTML='';}
}
function sauceSummaryHtml(card){
    const picker=card.querySelector('[data-sauce-picker]');
    if(!picker||picker.hidden)return '';
    const units=[...picker.querySelectorAll('[data-sauce-units] [data-sauce-unit]')];
    if(units.length){return units.map((unit,i)=>`<small>Unidad ${i+1} · Salsas: ${sauceEsc(describeSauceSelects(unit)||'sin salsas')}</small>`).join('');}
    const text=describeSauceSelects(picker.querySelector('[data-sauce-same]'));
    return text?`<small>Salsas: ${sauceEsc(text)}</small>`:'';
}
function generalSaucesSummaryHtml(enabled){
    const section=document.querySelector('[data-general-sauces]');
    if(!section||!enabled)return '';
    const names=[...section.querySelectorAll('input[type="checkbox"]:checked')].map(i=>i.closest('label').textContent.trim());
    return names.length?`<div class="summary-line"><span>Salsas generales<small>${sauceEsc(names.join(', '))}</small></span><strong>Aparte</strong></div>`:'';
}
</script>

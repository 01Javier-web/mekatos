{{-- Lógica compartida de opciones por unidad (jugos, bebidas y combos) para "Nuevo pedido" y "Editar".
     Las salsas por unidad siguen en sauce-script; esto no las toca. --}}
<script>
const UNIT_OPTION_FIELDS=['juice_preparation','juice_fruit','juice_other_fruit','beverage_option','combo','combo_beverage_type','combo_beverage_flavor'];
function unitOptionField(name){const m=String(name||'').match(/^([a-z_]+)\[\d+\]$/);return m&&UNIT_OPTION_FIELDS.includes(m[1])?m[1]:null;}
function sharedOptionsBlock(card){return card.querySelector('.options.juice,.options.combo,.options.beverage');}
function readOptionUnit(unit){const v={};unit.querySelectorAll('[data-field]').forEach(el=>{v[el.dataset.field]=el.value;});return v;}
function fillComboFlavors(unit,selected){const type=unit.querySelector('[data-field="combo_beverage_type"]'),flavor=unit.querySelector('[data-field="combo_beverage_flavor"]');if(!type||!flavor)return;const flavors=(typeof comboBeverages!=='undefined'&&comboBeverages[type.value]?.flavors)||[];flavor.innerHTML=flavors.map(f=>`<option value="${sauceEsc(f)}">${sauceEsc(f)}</option>`).join('');if(selected&&flavors.includes(selected))flavor.value=selected;}
function syncOptionUnitVisibility(unit){const combo=unit.querySelector('[data-field="combo"]'),extra=unit.querySelector('[data-unit-combo-extra]');if(combo&&extra)extra.hidden=combo.value!=='SI';const fruit=unit.querySelector('[data-field="juice_fruit"]'),other=unit.querySelector('[data-unit-other]');if(fruit&&other){other.hidden=fruit.value!=='OTRO';other.querySelector('input').disabled=fruit.value!=='OTRO';}}
function buildOptionUnit(card,productId,index,values){
    const shared=sharedOptionsBlock(card),unit=document.createElement('div');
    unit.className='option-unit';unit.setAttribute('data-option-unit','');
    unit.innerHTML=`<strong>Unidad ${index+1}</strong>`+shared.innerHTML;
    unit.querySelectorAll('select,input,textarea').forEach(el=>{const field=unitOptionField(el.getAttribute('name'));if(!field){el.closest('label')?.remove();return;}el.name=`unit_options[${productId}][${index}][${field}]`;el.dataset.field=field;['data-id','data-combo-type','data-combo-flavor'].forEach(a=>el.removeAttribute(a));el.classList.remove('combo-choice');el.disabled=false;});
    unit.querySelectorAll('[data-combo-extra]').forEach(x=>{x.removeAttribute('data-combo-extra');x.setAttribute('data-unit-combo-extra','');});
    const fruit=unit.querySelector('[data-field="juice_fruit"]');
    if(fruit&&!unit.querySelector('[data-field="juice_other_fruit"]')){fruit.closest('label').insertAdjacentHTML('afterend',`<label data-unit-other hidden>¿Qué fruta?<input type="text" maxlength="100" name="unit_options[${productId}][${index}][juice_other_fruit]" data-field="juice_other_fruit" placeholder="Escribe la fruta"></label>`);}
    // Valores: primero el tipo de bebida del combo (define los sabores), luego el resto.
    const type=unit.querySelector('[data-field="combo_beverage_type"]');
    if(type&&values.combo_beverage_type)type.value=values.combo_beverage_type;
    fillComboFlavors(unit,values.combo_beverage_flavor);
    unit.querySelectorAll('[data-field]').forEach(el=>{const f=el.dataset.field;if(f!=='combo_beverage_type'&&f!=='combo_beverage_flavor'&&values[f]!==undefined&&values[f]!==null)el.value=values[f];});
    syncOptionUnitVisibility(unit);
    unit.addEventListener('change',e=>{if(e.target.dataset.field==='combo_beverage_type')fillComboFlavors(unit);syncOptionUnitVisibility(unit);refresh();});
    unit.addEventListener('input',e=>{if(e.target.dataset.field==='juice_other_fruit')refresh();});
    return unit;
}
function renderOptionUnits(card,wrap,q){
    const units=wrap.querySelector('[data-option-units]'),current=[...units.querySelectorAll('[data-option-unit]')];
    if(current.length===q)return;
    // Conserva lo elegido en cada unidad; tras un error de validación, restaura lo enviado una vez.
    const saved=current.map(readOptionUnit),shared=sharedOptionsBlock(card),defaults={};
    shared.querySelectorAll('select,input,textarea').forEach(el=>{const f=unitOptionField(el.getAttribute('name'));if(f)defaults[f]=el.value;});
    let old={};if(!units.dataset.restored){old=sauceJson(wrap.dataset.oldUnits);units.dataset.restored='1';}
    units.innerHTML='';
    for(let i=0;i<q;i++)units.appendChild(buildOptionUnit(card,wrap.dataset.productId,i,saved[i]||old[i]||old[String(i)]||defaults));
}
function unitOptionLine(unit,base){
    const v=readOptionUnit(unit);let price=base,detail='';
    if('juice_preparation' in v){price=v.juice_preparation==='LECHE'?9500:8500;const fruit=unit.querySelector('[data-field="juice_fruit"]');detail=(v.juice_preparation==='LECHE'?'En leche':'En agua')+' · '+(v.juice_fruit==='OTRO'?'Otro: '+(v.juice_other_fruit||'…'):(fruit?.selectedOptions[0]?.text||''));}
    else if('combo' in v){if(v.combo==='SI'){price+=10000;detail='COMBO · '+(unit.querySelector('[data-field="combo_beverage_type"]')?.selectedOptions[0]?.text||'')+' · '+(v.combo_beverage_flavor||'');}else{detail='Sin combo';}}
    else if('beverage_option' in v){detail=v.beverage_option||'Sin opción elegida';}
    return {price,detail};
}
/** Sincroniza las opciones por unidad de una tarjeta. Devuelve {total, html} en "Personalizar individualmente". */
function syncUnitOptions(card,q){
    const wrap=card.querySelector('[data-unit-options]'),shared=sharedOptionsBlock(card);
    if(!wrap||!shared)return null;
    const mode=wrap.querySelector('[data-option-mode]'),canChoose=q>1;
    wrap.hidden=!canChoose;mode.disabled=!canChoose;
    const each=canChoose&&mode.value==='each';
    shared.querySelectorAll('select,input,textarea').forEach(el=>{el.disabled=each;});
    if(each){shared.hidden=true;shared.dataset.unitHidden='1';}
    else if(shared.dataset.unitHidden){delete shared.dataset.unitHidden;shared.hidden=shared.classList.contains('beverage')?q===0:false;}
    const units=wrap.querySelector('[data-option-units]');
    if(!each){units.innerHTML='';delete units.dataset.restored;return null;}
    renderOptionUnits(card,wrap,q);
    const base=Number(card.querySelector('input[data-id]').dataset.price);let total=0;const rows=[];
    units.querySelectorAll('[data-option-unit]').forEach((unit,i)=>{const line=unitOptionLine(unit,base);total+=line.price;rows.push(`<small>Unidad ${i+1} · ${sauceEsc(line.detail)} · ${money(line.price)}</small>`);});
    return {total,html:rows.join('')};
}
</script>

@extends('layouts.app')
@section('title', 'Nuevo pedido | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Operación</span>
            <h2>Nuevo pedido</h2>
            <p>Crea un pedido desde caja o desde el panel del mesero.</p>
        </div>
        <a class="button" href="{{ auth()->user()?->role?->value === 'MESERO' ? route('waiter.orders') : route('admin.orders.index') }}">Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-error"><strong>Revisa el pedido.</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.orders.store') }}" id="manual-order-form">
        @csrf
        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-header"><div><h3>1. Tipo de pedido</h3><span>Indica cómo se atenderá el cliente.</span></div></div>
            <div class="order-type-grid">
                <label class="order-type-option"><input type="radio" name="type" value="MESA" {{ old('type', 'MESA') === 'MESA' ? 'checked' : '' }}><span><strong>🪑 En mesa</strong><small>El pedido queda asociado a una mesa.</small></span></label>
                <label class="order-type-option"><input type="radio" name="type" value="PARA_LLEVAR" {{ old('type') === 'PARA_LLEVAR' ? 'checked' : '' }}><span><strong>🥡 Para llevar</strong><small>No requiere mesa ni sesión de mesa.</small></span></label>
            </div>
            <div id="table-field" style="margin-top:18px;">
                <label for="table_id">Mesa</label>
                <select id="table_id" name="table_id">
                    <option value="">Selecciona una mesa</option>
                    @foreach ($tables as $table)
                        <option value="{{ $table->id }}" {{ (string) old('table_id') === (string) $table->id ? 'selected' : '' }}>Mesa {{ $table->number }}{{ $table->name ? ' · '.$table->name : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="panel" style="margin-bottom:20px;">
            <div class="panel-header"><div><h3>2. Productos</h3><span>Agrega los productos disponibles.</span></div><strong id="items-count">0 productos</strong></div>
            <div class="manual-product-grid">
                @forelse ($products as $product)
                    <article class="manual-product-card">
                        <div><span class="eyebrow">{{ $product->category?->name ?? 'Menú' }}</span><h3>{{ $product->name }}</h3>@if($product->description)<p>{{ $product->description }}</p>@endif</div>
                        <div class="manual-product-bottom"><strong>${{ number_format($product->price, 0, ',', '.') }}</strong><div class="qty-control"><button type="button" data-action="minus" data-id="{{ $product->id }}">−</button><input type="number" name="items[{{ $product->id }}]" value="{{ old('items.'.$product->id, 0) }}" min="0" max="99" data-price="{{ $product->price }}" data-name="{{ $product->name }}" readonly><button type="button" data-action="plus" data-id="{{ $product->id }}">+</button></div></div>
                    </article>
                @empty
                    <div class="empty-state" style="grid-column:1/-1;">No hay productos disponibles.</div>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><div><h3>3. Confirmar</h3><span>Revisa el pedido antes de enviarlo a cocina.</span></div></div>
            <label for="notes">Notas</label>
            <textarea id="notes" name="notes" rows="3" maxlength="2000" placeholder="Ej. Sin cebolla, recoger en 15 minutos...">{{ old('notes') }}</textarea>
            <div id="order-summary" class="manual-order-summary"><p>Agrega productos para ver el resumen.</p></div>
            <div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px;"><a class="button" href="{{ auth()->user()?->role?->value === 'MESERO' ? route('waiter.orders') : route('admin.orders.index') }}">Cancelar</a><button class="button button-primary" type="submit" id="submit-order" disabled>Crear pedido</button></div>
        </div>
    </form>
</div>

<style>
.order-type-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.order-type-option{display:flex;gap:12px;align-items:flex-start;padding:18px;border:1px solid #e5e7eb;border-radius:12px;cursor:pointer}.order-type-option:has(input:checked){border-color:#111827;box-shadow:0 0 0 2px rgba(17,24,39,.08)}.order-type-option input{margin-top:4px}.order-type-option strong,.order-type-option small{display:block}.order-type-option small{color:#6b7280;margin-top:5px}.manual-product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.manual-product-card{border:1px solid #e5e7eb;border-radius:12px;padding:16px;display:flex;flex-direction:column;justify-content:space-between;gap:18px}.manual-product-card h3{margin:5px 0}.manual-product-card p{color:#6b7280;font-size:.9rem;margin:0}.manual-product-bottom{display:flex;justify-content:space-between;align-items:center;gap:10px}.qty-control{display:flex;align-items:center;border:1px solid #d1d5db;border-radius:9px;overflow:hidden}.qty-control button{border:0;background:#f3f4f6;width:34px;height:34px;font-size:20px;cursor:pointer}.qty-control input{border:0;text-align:center;width:38px;height:34px;background:#fff}.manual-order-summary{margin-top:16px;padding:16px;background:#f9fafb;border-radius:12px}.summary-line{display:flex;justify-content:space-between;gap:12px;padding:7px 0}.summary-total{display:flex;justify-content:space-between;border-top:1px solid #e5e7eb;margin-top:8px;padding-top:12px;font-size:1.1rem}.manual-order-summary p{margin:0;color:#6b7280}@media(max-width:850px){.manual-product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:600px){.order-type-grid,.manual-product-grid{grid-template-columns:1fr}}
</style>
<script>
const form = document.getElementById('manual-order-form');
const tableField = document.getElementById('table-field');
const tableSelect = document.getElementById('table_id');
const summary = document.getElementById('order-summary');
const submit = document.getElementById('submit-order');
const count = document.getElementById('items-count');
const money = value => new Intl.NumberFormat('es-CO',{style:'currency',currency:'COP',maximumFractionDigits:0}).format(value);

function refreshSummary(){
    let total=0, units=0, lines=[];
    document.querySelectorAll('.qty-control input').forEach(input=>{
        const qty=Number(input.value)||0;
        if(qty>0){ const subtotal=qty*Number(input.dataset.price); total+=subtotal; units+=qty; lines.push(`<div class="summary-line"><span>${qty}× ${input.dataset.name}</span><strong>${money(subtotal)}</strong></div>`); }
    });
    count.textContent=`${units} ${units===1?'producto':'productos'}`;
    submit.disabled=units===0;
    summary.innerHTML=units===0?'<p>Agrega productos para ver el resumen.</p>':`${lines.join('')}<div class="summary-total"><strong>Total</strong><strong>${money(total)}</strong></div>`;
}

document.querySelectorAll('[data-action]').forEach(button=>button.addEventListener('click',()=>{ const input=document.querySelector(`input[name="items[${button.dataset.id}]"]`); let value=Number(input.value)||0; value=button.dataset.action==='plus'?Math.min(99,value+1):Math.max(0,value-1); input.value=value; refreshSummary(); }));
document.querySelectorAll('input[name="type"]').forEach(radio=>radio.addEventListener('change',()=>{ const isTable=document.querySelector('input[name="type"]:checked').value==='MESA'; tableField.hidden=!isTable; if(!isTable)tableSelect.value=''; }));
form.addEventListener('submit',()=>{ submit.disabled=true; submit.textContent='Creando pedido...'; });
tableField.hidden=document.querySelector('input[name="type"]:checked').value!=='MESA';
refreshSummary();
</script>
@endsection

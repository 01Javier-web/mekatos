<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .client-feedback { position: fixed; right: 20px; bottom: 20px; z-index: 100; width: min(380px, calc(100% - 40px)); pointer-events: none; }
        .client-toast { display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; margin-top: 10px; background: #171717; color: #fff; border-radius: 12px; box-shadow: 0 12px 30px rgba(0,0,0,.22); opacity: 0; transform: translateY(12px); transition: opacity .2s ease, transform .2s ease; }
        .client-toast.show { opacity: 1; transform: translateY(0); }
        .client-toast-icon { flex: 0 0 28px; width: 28px; height: 28px; display: grid; place-items: center; border-radius: 50%; background: #fff; color: #171717; font-weight: 900; }
        .client-toast strong { display: block; margin-bottom: 2px; }
        .client-toast span { color: #d4d4d4; font-size: .88rem; line-height: 1.4; }
        .client-toast-error { background: #8b2424; }
        .client-toast-error .client-toast-icon { color: #8b2424; }
        .client-confirm { position: fixed; inset: 0; z-index: 90; display: grid; place-items: center; padding: 20px; background: rgba(0,0,0,.5); }
        .client-confirm[hidden] { display: none; }
        .client-confirm-card { width: min(480px, 100%); max-height: min(720px, calc(100vh - 40px)); overflow-y: auto; background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 18px 50px rgba(0,0,0,.22); }
        .client-confirm-card h2 { margin: 0 0 7px; color: #171717; }
        .client-confirm-card > p { margin: 0 0 18px; color: #737373; line-height: 1.45; }
        .confirm-summary { margin: 0 0 18px; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
        .confirm-line { display: flex; justify-content: space-between; gap: 16px; padding: 11px 13px; border-bottom: 1px solid #eee; }
        .confirm-line:last-child { border-bottom: 0; }
        .confirm-line span { color: #737373; }
        .confirm-total { display: flex; justify-content: space-between; padding: 15px 13px; background: #f7f7f7; font-size: 1.05rem; font-weight: 800; }
        .confirm-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .confirm-actions .button { width: 100%; }
        .cart-count-bump { animation: cart-bump .28s ease; }
        @keyframes cart-bump { 50% { transform: scale(1.12); } }
        @media (max-width: 560px) { .client-feedback { right: 12px; bottom: 12px; width: calc(100% - 24px); } .confirm-actions { grid-template-columns: 1fr; } }
        @media (prefers-reduced-motion: reduce) { .client-toast, .cart-count-bump { transition: none; animation: none; } }
    </style>
</head>
<body class="client-page">
<header class="client-header">
    <div><strong>Mekatos</strong><span id="table-label">Cargando mesa...</span></div>
    <button class="button button-primary" id="cart-open" type="button" aria-label="Abrir carrito">Carrito (<span id="cart-count">0</span>)</button>
</header>
<main class="client-shell">
    <div id="client-error" class="alert alert-error" hidden role="alert"></div>
    <section class="client-hero"><span class="eyebrow">Menú</span><h1>¿Qué quieres pedir?</h1><p>Selecciona tus productos y envía el pedido directamente a cocina.</p></section>
    <div id="menu-container" class="menu-container"><p class="loading-state">Cargando menú...</p></div>
</main>

<div id="cart-drawer" class="cart-drawer" hidden>
    <div class="cart-overlay" id="cart-close"></div>
    <aside class="cart-panel" aria-label="Carrito" aria-labelledby="cart-title">
        <div class="cart-panel-header"><h2 id="cart-title">Tu pedido</h2><button class="button button-small" id="cart-close-button" type="button">Cerrar</button></div>
        <div id="cart-items" class="cart-items"></div>
        <label class="client-notes"><span>Notas para el pedido</span><textarea id="order-notes" rows="3" placeholder="Ej. Sin cebolla..."></textarea></label>
        <div class="cart-total"><span>Total</span><strong id="cart-total">$0</strong></div>
        <button class="button button-primary client-submit" id="submit-order" type="button">Enviar pedido</button>
    </aside>
</div>

<div id="order-confirm" class="client-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="order-confirm-title">
    <div class="client-confirm-card">
        <h2 id="order-confirm-title">¿Confirmar pedido?</h2>
        <p>Revisa los productos y cantidades antes de enviarlo a cocina.</p>
        <div id="confirm-summary" class="confirm-summary"></div>
        <div class="confirm-actions">
            <button class="button" id="cancel-confirm" type="button">Seguir revisando</button>
            <button class="button button-primary" id="accept-confirm" type="button">Sí, enviar pedido</button>
        </div>
    </div>
</div>

<div id="confirmation" class="confirmation" hidden role="dialog" aria-modal="true" aria-labelledby="success-title">
    <div class="confirmation-card">
        <div class="confirmation-icon">✓</div>
        <h2 id="success-title">¡Pedido enviado!</h2>
        <p id="confirmation-text">Tu pedido fue recibido correctamente.</p>
        <button class="button button-primary" id="new-order" type="button">Volver al menú</button>
    </div>
</div>

<div id="client-feedback" class="client-feedback" aria-live="polite" aria-atomic="true"></div>

<script>
const TOKEN = @json($token);
const state = { table: null, products: [], cart: {} };
const money = value => '$' + Number(value).toLocaleString('es-CO', { maximumFractionDigits: 0 });
const showError = message => { const el=document.getElementById('client-error'); el.textContent=message; el.hidden=false; };
const hideError = () => document.getElementById('client-error').hidden=true;

function showToast(title, message, type='success') {
    const container = document.getElementById('client-feedback');
    const toast = document.createElement('div');
    toast.className = `client-toast ${type === 'error' ? 'client-toast-error' : ''}`;
    toast.innerHTML = `<div class="client-toast-icon">${type === 'error' ? '!' : '✓'}</div><div><strong>${escapeHtml(title)}</strong><span>${escapeHtml(message)}</span></div>`;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 220); }, 2800);
}

function renderMenu(categories){
    const container=document.getElementById('menu-container');
    const available=categories.map(c=>({...c, products:(c.products||[]).filter(p=>p.is_available)})).filter(c=>c.products.length);
    state.products=available.flatMap(c=>c.products);
    if(!available.length){container.innerHTML='<div class="empty-state">No hay productos disponibles en este momento.</div>';return;}
    container.innerHTML=available.map(c=>`<section class="menu-section"><div class="menu-section-heading"><h2>${escapeHtml(c.name)}</h2>${c.description?`<p>${escapeHtml(c.description)}</p>`:''}</div><div class="product-grid">${c.products.map(p=>`<article class="product-card"><div class="product-card-body"><h3>${escapeHtml(p.name)}</h3><p>${escapeHtml(p.description||'')}</p><strong>${money(p.price)}</strong></div><button class="button button-primary" type="button" onclick="addToCart(${p.id})">Agregar</button></article>`).join('')}</div></section>`).join('');
}

function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[s]));}

function getProduct(id){ return state.products.find(x=>x.id==id); }

function addToCart(id){
    const product = getProduct(id);
    if(!product) return;
    state.cart[id]=(state.cart[id]||0)+1;
    renderCart();
    animateCartCount();
    showToast('Producto agregado', `${product.name} · Cantidad: ${state.cart[id]}`);
}

function changeQty(id,delta){
    const product = getProduct(id);
    if(!product) return;
    const previous = state.cart[id] || 0;
    const next = previous + delta;
    if(next<=0){
        delete state.cart[id];
        showToast('Producto retirado', `${product.name} ya no está en tu carrito.`);
    } else {
        state.cart[id]=next;
        showToast(delta > 0 ? 'Cantidad aumentada' : 'Cantidad reducida', `${product.name} · Cantidad: ${next}`);
    }
    renderCart();
}

function animateCartCount(){
    const el=document.getElementById('cart-count');
    el.classList.remove('cart-count-bump');
    void el.offsetWidth;
    el.classList.add('cart-count-bump');
}

function renderCart(){
    const entries=Object.entries(state.cart);
    let total=0,count=0;
    const html=entries.map(([id,qty])=>{
        const p=getProduct(id);
        if(!p)return '';
        total+=Number(p.price)*qty;
        count+=qty;
        return `<div class="cart-item"><div><strong>${escapeHtml(p.name)}</strong><span>${money(p.price)} c/u · ${money(Number(p.price)*qty)} subtotal</span></div><div class="qty"><button type="button" aria-label="Reducir ${escapeHtml(p.name)}" onclick="changeQty(${p.id},-1)">−</button><span aria-label="Cantidad ${qty}">${qty}</span><button type="button" aria-label="Aumentar ${escapeHtml(p.name)}" onclick="changeQty(${p.id},1)">+</button></div></div>`;
    }).join('');
    document.getElementById('cart-items').innerHTML=html||'<p class="muted">Tu carrito está vacío. Agrega productos desde el menú.</p>';
    document.getElementById('cart-total').textContent=money(total);
    document.getElementById('cart-count').textContent=count;
    document.getElementById('submit-order').disabled=!entries.length;
}

function openCart(){
    document.getElementById('cart-drawer').hidden=false;
    renderCart();
}

function closeCart(){ document.getElementById('cart-drawer').hidden=true; }

function openOrderConfirmation(){
    const entries=Object.entries(state.cart);
    if(!entries.length){ showToast('Carrito vacío','Agrega al menos un producto antes de continuar.','error'); return; }
    let total=0;
    const lines=entries.map(([id,qty])=>{
        const p=getProduct(id);
        if(!p)return '';
        const subtotal=Number(p.price)*qty;
        total+=subtotal;
        return `<div class="confirm-line"><span>${qty} × ${escapeHtml(p.name)}</span><strong>${money(subtotal)}</strong></div>`;
    }).join('');
    const notes=document.getElementById('order-notes').value.trim();
    document.getElementById('confirm-summary').innerHTML=`${lines}${notes?`<div class="confirm-line"><span>Nota</span><strong>${escapeHtml(notes)}</strong></div>`:''}<div class="confirm-total"><span>Total</span><strong>${money(total)}</strong></div>`;
    document.getElementById('order-confirm').hidden=false;
}

function closeOrderConfirmation(){ document.getElementById('order-confirm').hidden=true; }

async function init(){
    try{
        const tableResponse=await fetch(`/api/table/${encodeURIComponent(TOKEN)}`);
        if(!tableResponse.ok)throw new Error('No se encontró la mesa.');
        state.table=await tableResponse.json();
        document.getElementById('table-label').textContent=`Mesa ${state.table.number}`;
        const menuResponse=await fetch('/api/menu');
        if(!menuResponse.ok)throw new Error('No fue posible cargar el menú.');
        renderMenu(await menuResponse.json());
    }catch(e){
        showError(e.message);
        document.getElementById('menu-container').innerHTML='';
    }
}

async function submitOrder(){
    hideError();
    const items=Object.entries(state.cart).map(([product_id,quantity])=>({product_id:Number(product_id),quantity}));
    if(!items.length){ showToast('Carrito vacío','Agrega al menos un producto antes de enviar.','error'); return; }
    closeOrderConfirmation();
    const button=document.getElementById('accept-confirm');
    button.disabled=true;
    button.textContent='Enviando pedido...';
    try{
        const response=await fetch('/api/orders',{
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json'},
            body:JSON.stringify({table_session_id:state.table.session_id,items,notes:document.getElementById('order-notes').value.trim()||null})
        });
        const data=await response.json();
        if(!response.ok)throw new Error(data.message||'No fue posible enviar el pedido.');
        closeCart();
        document.getElementById('confirmation-text').textContent=`Tu pedido #${data.order.id} fue recibido correctamente y enviado a cocina.`;
        document.getElementById('confirmation').hidden=false;
        state.cart={};
        document.getElementById('order-notes').value='';
        renderCart();
    }catch(e){
        showError(e.message);
        showToast('No se pudo enviar','Revisa tu conexión e inténtalo nuevamente.','error');
    }finally{
        button.disabled=false;
        button.textContent='Sí, enviar pedido';
    }
}

document.getElementById('cart-open').onclick=openCart;
document.getElementById('cart-close').onclick=closeCart;
document.getElementById('cart-close-button').onclick=closeCart;
document.getElementById('submit-order').onclick=openOrderConfirmation;
document.getElementById('cancel-confirm').onclick=closeOrderConfirmation;
document.getElementById('accept-confirm').onclick=submitOrder;
document.getElementById('new-order').onclick=()=>document.getElementById('confirmation').hidden=true;
document.getElementById('order-confirm').addEventListener('click', event => { if(event.target.id === 'order-confirm') closeOrderConfirmation(); });
document.addEventListener('keydown', event => { if(event.key === 'Escape'){ closeOrderConfirmation(); if(!document.getElementById('confirmation').hidden) document.getElementById('confirmation').hidden=true; } });
renderCart();
init();
</script>
</body>
</html>

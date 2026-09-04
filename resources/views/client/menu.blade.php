<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="client-page">
<header class="client-header"><div><strong>Mekatos</strong><span id="table-label">Cargando mesa...</span></div><button class="button button-primary" id="cart-open">Carrito (<span id="cart-count">0</span>)</button></header>
<main class="client-shell">
    <div id="client-error" class="alert alert-error" hidden></div>
    <section class="client-hero"><span class="eyebrow">Menú</span><h1>¿Qué quieres pedir?</h1><p>Selecciona tus productos y envía el pedido directamente a cocina.</p></section>
    <div id="menu-container" class="menu-container"><p class="loading-state">Cargando menú...</p></div>
</main>
<div id="cart-drawer" class="cart-drawer" hidden>
    <div class="cart-overlay" id="cart-close"></div>
    <aside class="cart-panel" aria-label="Carrito">
        <div class="cart-panel-header"><h2>Tu pedido</h2><button class="button button-small" id="cart-close-button">Cerrar</button></div>
        <div id="cart-items" class="cart-items"></div>
        <label class="client-notes"><span>Notas para el pedido</span><textarea id="order-notes" rows="3" placeholder="Ej. Sin cebolla..."></textarea></label>
        <div class="cart-total"><span>Total</span><strong id="cart-total">$0</strong></div>
        <button class="button button-primary client-submit" id="submit-order">Enviar pedido</button>
    </aside>
</div>
<div id="confirmation" class="confirmation" hidden><div class="confirmation-card"><div class="confirmation-icon">✓</div><h2>¡Pedido enviado!</h2><p id="confirmation-text">Tu pedido fue recibido correctamente.</p><button class="button button-primary" id="new-order">Volver al menú</button></div></div>
<script>
const TOKEN = @json($token);
const state = { table: null, products: [], cart: {} };
const money = value => '$' + Number(value).toLocaleString('es-CO', { maximumFractionDigits: 0 });
const showError = message => { const el=document.getElementById('client-error'); el.textContent=message; el.hidden=false; };
const hideError = () => document.getElementById('client-error').hidden=true;
function renderMenu(categories){
    const container=document.getElementById('menu-container');
    const available=categories.map(c=>({...c, products:(c.products||[]).filter(p=>p.is_available)})).filter(c=>c.products.length);
    state.products=available.flatMap(c=>c.products);
    if(!available.length){container.innerHTML='<div class="empty-state">No hay productos disponibles en este momento.</div>';return;}
    container.innerHTML=available.map(c=>`<section class="menu-section"><div class="menu-section-heading"><h2>${escapeHtml(c.name)}</h2>${c.description?`<p>${escapeHtml(c.description)}</p>`:''}</div><div class="product-grid">${c.products.map(p=>`<article class="product-card"><div class="product-card-body"><h3>${escapeHtml(p.name)}</h3><p>${escapeHtml(p.description||'')}</p><strong>${money(p.price)}</strong></div><button class="button button-primary" onclick="addToCart(${p.id})">Agregar</button></article>`).join('')}</div></section>`).join('');
}
function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[s]));}
function addToCart(id){state.cart[id]=(state.cart[id]||0)+1;renderCart();}
function changeQty(id,delta){state.cart[id]=(state.cart[id]||0)+delta;if(state.cart[id]<=0)delete state.cart[id];renderCart();}
function renderCart(){const entries=Object.entries(state.cart);let total=0,count=0;const html=entries.map(([id,qty])=>{const p=state.products.find(x=>x.id==id);if(!p)return '';total+=Number(p.price)*qty;count+=qty;return `<div class="cart-item"><div><strong>${escapeHtml(p.name)}</strong><span>${money(p.price)} c/u</span></div><div class="qty"><button onclick="changeQty(${p.id},-1)">−</button><span>${qty}</span><button onclick="changeQty(${p.id},1)">+</button></div></div>`}).join('');document.getElementById('cart-items').innerHTML=html||'<p class="muted">Tu carrito está vacío.</p>';document.getElementById('cart-total').textContent=money(total);document.getElementById('cart-count').textContent=count;document.getElementById('submit-order').disabled=!entries.length;}
function openCart(){document.getElementById('cart-drawer').hidden=false;}
function closeCart(){document.getElementById('cart-drawer').hidden=true;}
async function init(){try{const tableResponse=await fetch(`/api/table/${encodeURIComponent(TOKEN)}`);if(!tableResponse.ok)throw new Error('No se encontró la mesa.');state.table=await tableResponse.json();document.getElementById('table-label').textContent=`Mesa ${state.table.number}`;const menuResponse=await fetch('/api/menu');if(!menuResponse.ok)throw new Error('No fue posible cargar el menú.');renderMenu(await menuResponse.json());}catch(e){showError(e.message);document.getElementById('menu-container').innerHTML='';}}
async function submitOrder(){hideError();const items=Object.entries(state.cart).map(([product_id,quantity])=>({product_id:Number(product_id),quantity}));if(!items.length)return;const button=document.getElementById('submit-order');button.disabled=true;button.textContent='Enviando...';try{const response=await fetch('/api/orders',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({table_session_id:state.table.session_id,items,notes:document.getElementById('order-notes').value||null})});const data=await response.json();if(!response.ok)throw new Error(data.message||'No fue posible enviar el pedido.');closeCart();document.getElementById('confirmation-text').textContent=`Tu pedido #${data.order.id} fue recibido correctamente.`;document.getElementById('confirmation').hidden=false;state.cart={};document.getElementById('order-notes').value='';renderCart();}catch(e){showError(e.message);button.disabled=false;button.textContent='Enviar pedido';}}
document.getElementById('cart-open').onclick=openCart;document.getElementById('cart-close').onclick=closeCart;document.getElementById('cart-close-button').onclick=closeCart;document.getElementById('submit-order').onclick=submitOrder;document.getElementById('new-order').onclick=()=>document.getElementById('confirmation').hidden=true;renderCart();init();
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#171717">
    <title>Menú | Mekatos</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        .client-tools{position:sticky;top:66px;z-index:40;padding:10px 0 12px;background:#f7f7f5}.client-tools-inner{display:flex;gap:10px;align-items:center}.menu-search{flex:1;min-height:44px;padding:10px 13px;border:1px solid #d8d8d5;border-radius:10px;background:#fff;color:#171717}.category-nav{display:flex;gap:7px;overflow-x:auto;padding:1px 0 3px;scrollbar-width:thin}.category-nav a{flex:0 0 auto;padding:8px 11px;border:1px solid #dededb;border-radius:999px;background:#fff;text-decoration:none;font-size:.78rem;font-weight:750;color:#555}.category-nav a:hover,.category-nav a.active{background:#171717;border-color:#171717;color:#fff}.search-empty{padding:50px 20px;text-align:center;background:#fff;border:1px solid #e5e5e2;border-radius:15px}.search-empty h2{margin:0 0 6px}.search-empty p{margin:0 0 18px;color:#777}.product-card[hidden],.menu-section[hidden]{display:none}.client-footer{text-align:center;color:#888;font-size:.78rem;padding:0 0 25px}.cart-count-label{white-space:nowrap}.client-header{transition:box-shadow .2s ease}.client-hero{padding:8px 0 0}.product-card{min-height:225px}.product-card.is-in-cart{border-color:#aaa;box-shadow:0 10px 28px rgba(0,0,0,.07)}.product-card .product-category-mark{display:inline-flex;width:30px;height:30px;align-items:center;justify-content:center;margin-bottom:10px;border-radius:9px;background:#f0f0ee;font-size:.9rem}.product-card .button[data-add]{position:relative}.client-feedback{z-index:120}.cart-panel{display:flex;flex-direction:column}.cart-items{flex:1}.cart-empty-action{margin-top:10px}.cart-panel-footer{margin-top:auto}.client-confirm-card{max-height:min(90vh,720px);overflow:auto}.confirm-line strong:last-child{overflow-wrap:anywhere;text-align:right}.confirm-actions{position:sticky;bottom:0;padding-top:12px;background:#fff}.client-page .button:focus-visible,.client-page input:focus-visible,.client-page textarea:focus-visible,.client-page a:focus-visible{outline:3px solid rgba(23,23,23,.2);outline-offset:2px}
        @media(max-width:760px){.client-tools{top:59px}.client-tools-inner{flex-direction:column;align-items:stretch}.menu-search{width:100%}.client-hero h1{font-size:2rem}.product-card{min-height:205px}.cart-panel{padding:18px}.client-header{padding-inline:12px}.client-header strong{font-size:1.1rem}.client-header .button{min-height:38px;padding-inline:11px}}
        @media(max-width:430px){.cart-count-label{display:none}.client-shell{width:min(100% - 20px,1240px)}.client-hero{padding-top:2px}.menu-section-heading{align-items:flex-start;flex-direction:column;gap:5px}.product-card{padding:15px}.confirm-actions{display:grid;grid-template-columns:1fr}.confirm-actions .button{width:100%}}
        @media(prefers-reduced-motion:reduce){.product-card,.client-header{transition:none}}
    </style>
</head>
<body class="client-page">
<header class="client-header">
    <div><strong>Mekatos</strong><span id="table-label">Cargando mesa...</span></div>
    <button class="button button-primary" id="cart-open" type="button" aria-label="Abrir carrito">🛒 <span class="cart-count-label">Carrito</span> (<span id="cart-count">0</span>)</button>
</header>
<main class="client-shell">
    <div id="client-error" class="alert alert-error" hidden role="alert"></div>
    <section class="client-hero"><span class="eyebrow">Menú digital</span><h1>¿Qué quieres pedir?</h1><p>Explora el menú, arma tu pedido y envíalo directamente a cocina.</p></section>
    <div class="client-tools" aria-label="Herramientas del menú">
        <div class="client-tools-inner"><input class="menu-search" id="menu-search" type="search" placeholder="Buscar hamburguesa, pollo, jugo..." autocomplete="off" aria-label="Buscar en el menú"></div>
        <nav class="category-nav" id="category-nav" aria-label="Categorías del menú"></nav>
    </div>
    <div id="menu-container" class="menu-container"><p class="loading-state">Cargando menú...</p></div>
    <div id="search-empty" class="search-empty" hidden><h2>No encontramos ese producto</h2><p>Prueba con otro nombre o revisa las categorías del menú.</p><button class="button" type="button" id="clear-search">Limpiar búsqueda</button></div>
</main>
<footer class="client-footer">Mekatos · Gracias por tu pedido</footer>

<div id="cart-drawer" class="cart-drawer" hidden>
    <div class="cart-overlay" id="cart-close"></div>
    <aside class="cart-panel" aria-label="Carrito" aria-labelledby="cart-title">
        <div class="cart-panel-header"><div><span class="eyebrow">Tu selección</span><h2 id="cart-title">Tu pedido</h2></div><button class="button button-small" id="cart-close-button" type="button">Cerrar</button></div>
        <div id="cart-items" class="cart-items"></div>
        <div class="cart-panel-footer">
            <label class="client-notes"><span>Notas para el pedido</span><textarea id="order-notes" rows="3" maxlength="2000" placeholder="Ej. Sin cebolla, sin salsa..."></textarea></label>
            <div class="cart-total"><span>Total</span><strong id="cart-total">$0</strong></div>
            <button class="button button-primary client-submit" id="submit-order" type="button">Continuar</button>
        </div>
    </aside>
</div>

<div id="order-confirm" class="client-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="order-confirm-title">
    <div class="client-confirm-card">
        <h2 id="order-confirm-title">¿Confirmar pedido?</h2><p>Revisa productos, cantidades y notas antes de enviarlo a cocina.</p>
        <div id="confirm-summary" class="confirm-summary"></div>
        <div class="confirm-actions"><button class="button" id="cancel-confirm" type="button">Seguir revisando</button><button class="button button-primary" id="accept-confirm" type="button">Sí, enviar pedido</button></div>
    </div>
</div>

<div id="confirmation" class="confirmation" hidden role="dialog" aria-modal="true" aria-labelledby="success-title">
    <div class="confirmation-card"><div class="confirmation-icon">✓</div><h2 id="success-title">¡Pedido enviado!</h2><p id="confirmation-text">Tu pedido fue recibido correctamente.</p><button class="button button-primary" id="new-order" type="button">Volver al menú</button></div>
</div>
<div id="client-feedback" class="client-feedback" aria-live="polite" aria-atomic="true"></div>

<script>
const TOKEN = @json($token);
const STORAGE_KEY = `mekatos-cart-${TOKEN}`;
const state = { table:null, products:[], cart:{}, categories:[] };
const money = value => '$' + Number(value).toLocaleString('es-CO',{maximumFractionDigits:0});
const showError = message => { const el=document.getElementById('client-error'); el.textContent=message; el.hidden=false; };
const hideError = () => document.getElementById('client-error').hidden=true;
function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[s]));}
function saveCart(){localStorage.setItem(STORAGE_KEY,JSON.stringify({cart:state.cart,notes:document.getElementById('order-notes').value}));}
function restoreCart(){try{const saved=JSON.parse(localStorage.getItem(STORAGE_KEY)||'null');if(saved?.cart)state.cart=saved.cart;document.getElementById('order-notes').value=saved?.notes||'';}catch{state.cart={};}}
function clearSavedCart(){localStorage.removeItem(STORAGE_KEY);}
function showToast(title,message,type='success'){const container=document.getElementById('client-feedback');const toast=document.createElement('div');toast.className=`client-toast ${type==='error'?'client-toast-error':''}`;toast.innerHTML=`<div class="client-toast-icon">${type==='error'?'!':'✓'}</div><div><strong>${escapeHtml(title)}</strong><span>${escapeHtml(message)}</span></div>`;container.appendChild(toast);requestAnimationFrame(()=>toast.classList.add('show'));setTimeout(()=>{toast.classList.remove('show');setTimeout(()=>toast.remove(),220)},2800);}
function categoryIcon(name){const n=name.toLowerCase();if(n.includes('hamburg'))return'🍔';if(n.includes('pollo'))return'🍗';if(n.includes('perro'))return'🌭';if(n.includes('salchip'))return'🍟';if(n.includes('pizza'))return'🍕';if(n.includes('bebida')||n.includes('jugo')||n.includes('gaseosa'))return'🥤';if(n.includes('cerveza'))return'🍺';if(n.includes('ensalada'))return'🥗';if(n.includes('carne')||n.includes('parrilla'))return'🥩';return'🍽️';}
function renderCategoryNav(categories){const nav=document.getElementById('category-nav');nav.innerHTML=categories.map(c=>`<a href="#category-${c.id}" data-category="${c.id}">${escapeHtml(c.name)}</a>`).join('');nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>nav.querySelectorAll('a').forEach(x=>x.classList.remove('active'))));}
function renderMenu(categories){const container=document.getElementById('menu-container');const available=categories.map(c=>({...c,products:(c.products||[]).filter(p=>p.is_available)})).filter(c=>c.products.length);state.categories=available;state.products=available.flatMap(c=>c.products);renderCategoryNav(available);if(!available.length){container.innerHTML='<div class="empty-state">No hay productos disponibles en este momento.</div>';return;}container.innerHTML=available.map(c=>`<section class="menu-section" id="category-${c.id}" data-category-name="${escapeHtml(c.name).toLowerCase()}"><div class="menu-section-heading"><div><h2>${escapeHtml(c.name)}</h2>${c.description?`<p>${escapeHtml(c.description)}</p>`:''}</div><span class="muted">${c.products.length} ${c.products.length===1?'opción':'opciones'}</span></div><div class="product-grid">${c.products.map(p=>`<article class="product-card" data-product-name="${escapeHtml((p.name+' '+(p.description||'')+' '+c.name).toLowerCase())}" data-product-id="${p.id}"><div><span class="product-category-mark" aria-hidden="true">${categoryIcon(c.name)}</span><h3>${escapeHtml(p.name)}</h3><p>${escapeHtml(p.description||'')}</p><strong>${money(p.price)}</strong></div><button class="button button-primary" type="button" data-add="${p.id}">Agregar</button></article>`).join('')}</div></section>`).join('');container.querySelectorAll('[data-add]').forEach(btn=>btn.addEventListener('click',()=>addToCart(Number(btn.dataset.add))));refreshProductStates();}
function getProduct(id){return state.products.find(x=>x.id==id)}
function refreshProductStates(){document.querySelectorAll('.product-card[data-product-id]').forEach(card=>{const id=card.dataset.productId,qty=Number(state.cart[id]||0),button=card.querySelector('[data-add]');card.classList.toggle('is-in-cart',qty>0);if(button)button.textContent=qty>0?`Agregar · ${qty}`:'Agregar';});}
function addToCart(id){const p=getProduct(id);if(!p)return;state.cart[id]=(state.cart[id]||0)+1;saveCart();renderCart();refreshProductStates();animateCartCount();showToast('Producto agregado',`${p.name} · Cantidad: ${state.cart[id]}`);}
function changeQty(id,delta){const p=getProduct(id);if(!p)return;const next=(state.cart[id]||0)+delta;if(next<=0){delete state.cart[id];showToast('Producto retirado',`${p.name} salió de tu carrito.`)}else{state.cart[id]=next;showToast(delta>0?'Cantidad aumentada':'Cantidad reducida',`${p.name} · Cantidad: ${next}`)}saveCart();renderCart();refreshProductStates();}
function animateCartCount(){const el=document.getElementById('cart-count');el.classList.remove('cart-count-bump');void el.offsetWidth;el.classList.add('cart-count-bump');}
function renderCart(){const entries=Object.entries(state.cart);let total=0,count=0;const html=entries.map(([id,qty])=>{const p=getProduct(id);if(!p)return '';total+=Number(p.price)*qty;count+=qty;return `<div class="cart-item"><div><strong>${escapeHtml(p.name)}</strong><span>${money(p.price)} c/u · ${money(Number(p.price)*qty)}</span></div><div class="qty"><button type="button" aria-label="Reducir ${escapeHtml(p.name)}" data-qty="-1" data-id="${p.id}">−</button><span>${qty}</span><button type="button" aria-label="Aumentar ${escapeHtml(p.name)}" data-qty="1" data-id="${p.id}">+</button></div></div>`}).join('');document.getElementById('cart-items').innerHTML=html||'<div class="empty-state"><p>Tu carrito está vacío.</p><button class="button cart-empty-action" type="button" id="empty-close">Volver al menú</button></div>';document.getElementById('cart-total').textContent=money(total);document.getElementById('cart-count').textContent=count;document.getElementById('submit-order').disabled=!entries.length;document.querySelectorAll('[data-qty]').forEach(btn=>btn.addEventListener('click',()=>changeQty(Number(btn.dataset.id),Number(btn.dataset.qty))));document.getElementById('empty-close')?.addEventListener('click',closeCart);}
function filterMenu(term){const q=term.trim().toLowerCase();let matches=0;document.querySelectorAll('.menu-section').forEach(section=>{let sectionMatches=0;section.querySelectorAll('.product-card').forEach(card=>{const hit=!q||card.dataset.productName.includes(q);card.hidden=!hit;if(hit)sectionMatches++});section.hidden=sectionMatches===0;matches+=sectionMatches});document.getElementById('search-empty').hidden=matches!==0||!q;document.querySelectorAll('.category-nav a').forEach(a=>a.classList.remove('active'));}
function openCart(){document.getElementById('cart-drawer').hidden=false;renderCart();document.getElementById('cart-close-button').focus();document.body.style.overflow='hidden';}
function closeCart(){document.getElementById('cart-drawer').hidden=true;document.body.style.overflow='';}
function openOrderConfirmation(){const entries=Object.entries(state.cart);if(!entries.length){showToast('Carrito vacío','Agrega al menos un producto antes de continuar.','error');return;}let total=0;const lines=entries.map(([id,qty])=>{const p=getProduct(id);if(!p)return '';const subtotal=Number(p.price)*qty;total+=subtotal;return `<div class="confirm-line"><span>${qty} × ${escapeHtml(p.name)}</span><strong>${money(subtotal)}</strong></div>`}).join('');const notes=document.getElementById('order-notes').value.trim();document.getElementById('confirm-summary').innerHTML=`${lines}${notes?`<div class="confirm-line"><span>Nota</span><strong>${escapeHtml(notes)}</strong></div>`:''}<div class="confirm-total"><span>Total</span><strong>${money(total)}</strong></div>`;document.getElementById('order-confirm').hidden=false;document.getElementById('accept-confirm').focus();}
function closeOrderConfirmation(){document.getElementById('order-confirm').hidden=true;}
async function init(){try{const tableResponse=await fetch(`/api/table/${encodeURIComponent(TOKEN)}`,{headers:{Accept:'application/json'}});if(!tableResponse.ok)throw new Error('No se encontró esta mesa.');state.table=await tableResponse.json();document.getElementById('table-label').textContent=`Mesa ${state.table.number}`;const menuResponse=await fetch('/api/menu',{headers:{Accept:'application/json'}});if(!menuResponse.ok)throw new Error('No fue posible cargar el menú.');renderMenu(await menuResponse.json());restoreCart();renderCart();refreshProductStates();}catch(e){showError(e.message);document.getElementById('menu-container').innerHTML='';}}
async function submitOrder(){hideError();const items=Object.entries(state.cart).map(([product_id,quantity])=>({product_id:Number(product_id),quantity:Number(quantity)}));if(!items.length){showToast('Carrito vacío','Agrega al menos un producto antes de enviar.','error');return;}const button=document.getElementById('accept-confirm');button.disabled=true;button.textContent='Enviando pedido...';try{const response=await fetch('/api/orders',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({table_session_id:state.table.session_id,items,notes:document.getElementById('order-notes').value.trim()||null})});const data=await response.json();if(!response.ok)throw new Error(data.message||'No fue posible enviar el pedido.');closeOrderConfirmation();closeCart();document.getElementById('confirmation-text').textContent=`Tu pedido #${data.order.id} fue recibido correctamente y enviado a cocina.`;document.getElementById('confirmation').hidden=false;state.cart={};document.getElementById('order-notes').value='';clearSavedCart();renderCart();refreshProductStates();}catch(e){showError(e.message);showToast('No se pudo enviar','Revisa tu conexión e inténtalo nuevamente.','error');}finally{button.disabled=false;button.textContent='Sí, enviar pedido';}}
document.getElementById('cart-open').onclick=openCart;document.getElementById('cart-close').onclick=closeCart;document.getElementById('cart-close-button').onclick=closeCart;document.getElementById('submit-order').onclick=openOrderConfirmation;document.getElementById('cancel-confirm').onclick=closeOrderConfirmation;document.getElementById('accept-confirm').onclick=submitOrder;document.getElementById('new-order').onclick=()=>document.getElementById('confirmation').hidden=true;document.getElementById('order-notes').addEventListener('input',saveCart);document.getElementById('menu-search').addEventListener('input',e=>filterMenu(e.target.value));document.getElementById('clear-search').onclick=()=>{const input=document.getElementById('menu-search');input.value='';filterMenu('');input.focus()};document.getElementById('order-confirm').addEventListener('click',e=>{if(e.target.id==='order-confirm')closeOrderConfirmation()});document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeOrderConfirmation();closeCart();if(!document.getElementById('confirmation').hidden)document.getElementById('confirmation').hidden=true}});renderCart();init();
</script>
</body>
</html>

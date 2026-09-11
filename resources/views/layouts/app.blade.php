<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#BB2528">
    <title>@yield('title', 'Mekatos')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        :root{--mk-red:#BB2528;--mk-red-dark:#951d20;--mk-red-soft:#f7d9d6;--mk-mustard:#E3B83C;--mk-mustard-light:#f6d96c;--mk-cream:#fff8df;--mk-ink:#2b2118;--mk-muted:#6f6254;--mk-border:#e2d5ae;--mk-card:#fffdf7}
        body{background:var(--mk-mustard);color:var(--mk-ink)}
        .main-header{background:var(--mk-red);color:#fff;border-bottom:1px solid var(--mk-red-dark);box-shadow:0 5px 20px rgba(92,25,25,.22);backdrop-filter:saturate(120%) blur(8px)}
        .brand{color:#fff;margin-left:-4px;align-items:center;align-self:center}.brand-mark{background:var(--mk-cream);color:var(--mk-red);box-shadow:0 2px 8px rgba(0,0,0,.12)}.brand small{color:#ffe9e6}
        .main-nav a{color:#fff4f2}.main-nav a:hover,.main-nav a:focus-visible{color:#fff;background:rgba(255,255,255,.14)}
        .main-nav .nav-primary{background:var(--mk-cream);color:var(--mk-red);font-weight:850;box-shadow:0 2px 7px rgba(70,15,15,.15)}.main-nav .nav-primary:hover{background:#fffdf1;color:var(--mk-red-dark)}
        .main-nav a[aria-current="page"]{color:#fff;background:rgba(255,255,255,.16);font-weight:800}.main-nav .nav-primary[aria-current="page"]{background:var(--mk-cream);color:var(--mk-red)}
        .user-menu{position:relative;margin-left:auto;display:flex;align-items:center}.user-chip{border-left-color:rgba(255,255,255,.28);background:transparent;color:#fff;border-top:0;border-right:0;border-bottom:0;cursor:pointer;font:inherit;text-align:left;padding:7px 12px 7px 16px;display:flex;flex-direction:column;align-items:flex-start}.user-chip strong{color:#fff}.user-chip small{color:#ffe9e6}.user-chip:hover,.user-chip:focus-visible{background:rgba(255,255,255,.12);outline:none}.user-chip::after{content:'⌄';font-size:.72rem;margin-left:auto;position:absolute;right:2px;top:50%;transform:translateY(-50%);color:#ffe9e6}.user-menu.open .user-chip::after{content:'⌃'}
        .logout-form{position:absolute;right:0;top:calc(100% + 7px);min-width:145px;padding:5px;background:var(--mk-card);border:1px solid var(--mk-border);border-radius:10px;box-shadow:0 12px 30px rgba(70,18,18,.22);z-index:50;display:none}.user-menu.open .logout-form{display:block}.nav-logout{width:100%;border:0;border-radius:7px;background:transparent;color:var(--mk-red-dark);padding:9px 11px;text-align:left;cursor:pointer;font:inherit;font-weight:700}.nav-logout:hover,.nav-logout:focus-visible{background:var(--mk-red-soft);color:var(--mk-red-dark);outline:none}
        .mobile-nav-toggle{border-color:rgba(255,255,255,.35);background:var(--mk-red-dark);color:#fff}
        .main-nav{row-gap:5px}.mobile-nav-toggle{align-items:center;gap:5px}
        .page-shell{padding-top:36px}.page-heading{margin-bottom:28px}.eyebrow{color:#675238}.page-heading h2{color:var(--mk-ink)}.page-heading p{color:#665b4d}
        .panel,.form-card{background:var(--mk-card);border-color:var(--mk-border);box-shadow:0 9px 28px rgba(83,58,15,.10)}
        .panel-header{border-bottom-color:#eadfbf}.panel-header h3{color:var(--mk-ink)}.panel-header span{color:var(--mk-muted)}
        .data-table th{background:#f8e7ae;color:#665238}.data-table td{border-bottom-color:#eadfbf}.data-table tbody tr:hover{background:#fff9e8}
        .button{border-color:#d7c89e;background:#fffdf7;color:var(--mk-ink);box-shadow:0 2px 5px rgba(70,48,10,.05)}.button:hover{background:#fff7df;border-color:#c9b77f}
        .button-primary{background:var(--mk-red);border-color:var(--mk-red);color:#fff;box-shadow:0 3px 8px rgba(122,25,25,.20)}.button-primary:hover{background:var(--mk-red-dark);color:#fff}
        .button-danger{border-color:#e1aaaa;color:var(--mk-red-dark);background:#fff8f7}.button-danger:hover{background:#ffeceb}
        .button:focus-visible,.form-card input:focus-visible,.form-card select:focus-visible,.form-card textarea:focus-visible,.filter-form select:focus-visible{outline:3px solid rgba(187,37,40,.22);outline-offset:2px}
        .alert-success{background:#edf7e8;border-color:#b9d9a9;color:#315d24}.alert-error{background:#fff0ef;border-color:#e3b0ad;color:#8f2527}
        .empty-state{color:#6f6254}.empty-state h3{color:var(--mk-ink)}
        .form-card input,.form-card select,.form-card textarea,.manual-order-form textarea,.filter-form select,.status-form select{border-color:#d7c89e;background:#fffdf7;color:var(--mk-ink)}
        .stat-card{background:var(--mk-card);border-color:var(--mk-border);box-shadow:0 7px 22px rgba(83,58,15,.09)}.stat-card:hover{box-shadow:0 11px 28px rgba(83,58,15,.14)}.stat-card span{color:#6f6254}.stat-card strong{color:var(--mk-red)}.stat-card small{display:block;margin-top:6px;color:#6f6254;font-size:.74rem;line-height:1.35}
        .status-order{background:#f7e8c4;color:#75551d}.status-active{background:#e8f4df;color:#315d24}.status-inactive{background:#eee7d4;color:#6b5d49}.status-role{background:#f2e8c8;color:#604a27}
        .info-box{background:#f8e9b9;border-color:#e4d5a8}.muted,.history-item span{color:#6f6254}
        .waiter-card{background:var(--mk-card);border-color:var(--mk-border);box-shadow:0 7px 22px rgba(83,58,15,.09)}.waiter-card h3{color:var(--mk-red)}
        .login-page{background:radial-gradient(circle at top,#fffdf4 0,#f4d46a 70%,#E3B83C 100%)}.login-card{background:var(--mk-card);border-color:var(--mk-border);box-shadow:0 18px 55px rgba(83,58,15,.18)}.login-brand{color:var(--mk-red)}.login-card h1{color:var(--mk-ink)}.login-subtitle,.login-eyebrow{color:#6f6254}.login-form input{border-color:#d7c89e;background:#fffdf7}.login-button{background:var(--mk-red);border-color:var(--mk-red)}.login-button:hover{background:var(--mk-red-dark)}
        .client-page{min-height:100vh;background:var(--mk-mustard)}.client-header{background:var(--mk-red);color:#fff;box-shadow:0 4px 18px rgba(92,25,25,.22)}.client-header span{color:#ffe9e6}.client-hero h1{color:var(--mk-ink)}.client-hero p{color:#665b4d}
        .menu-section-heading{border-bottom-color:#d8c78f}.menu-section-heading h2{color:var(--mk-ink)}.menu-section-heading p{color:#6f6254}
        .product-card{background:var(--mk-card);border-color:var(--mk-border);box-shadow:0 7px 20px rgba(83,58,15,.09)}.product-card:hover{box-shadow:0 12px 28px rgba(83,58,15,.14)}.product-card h3{color:var(--mk-ink)}.product-card p{color:#6f6254}.product-card strong{color:var(--mk-red)}.product-card .button{background:var(--mk-red);border-color:var(--mk-red);color:#fff}.product-card .button:hover{background:var(--mk-red-dark);color:#fff}
        .cart-panel,.confirmation-card,.client-confirm-card{background:var(--mk-card)}.cart-panel-header h2,.client-confirm-card h2,.confirmation-card h2{color:var(--mk-ink)}.cart-item,.confirm-summary{border-color:#eadfbf}.cart-total{border-top-color:#d9c99d}.confirm-total{background:#f8e7ae}.confirmation-icon{background:#edf7e8;color:#315d24}
        .client-feedback .client-toast{background:#33271d}.client-toast-icon{color:var(--mk-red)}.client-toast-error{background:#8f2527}
        .tables-legend{padding-left:6px}
        @media(max-width:980px){.header-inner{gap:12px}.main-nav a{padding-inline:8px}.user-chip{padding-inline:7px}}
        @media(max-width:760px){
            .header-inner{min-height:60px;width:min(100% - 24px,1240px)}
            .mobile-nav-toggle{display:inline-flex;margin-left:auto}
            .main-nav{display:none;position:absolute;left:12px;right:12px;top:58px;padding:10px;background:var(--mk-red);border:1px solid rgba(255,255,255,.28);border-radius:14px;box-shadow:0 18px 45px rgba(70,18,18,.30)}
            .main-nav.is-open{display:grid;grid-template-columns:1fr 1fr;gap:5px}.main-nav a,.main-nav .nav-primary{padding:11px 12px;text-align:left}.main-nav .nav-primary{grid-column:1/-1}
            .user-menu{grid-column:1/-1;justify-content:stretch;margin:5px 0 0}.user-chip{width:100%;border-left:0;border-top:1px solid rgba(255,255,255,.28);margin:0;padding:10px 18px 5px 5px}.user-chip::after{right:7px}.logout-form{position:static;min-width:0;width:100%;margin-top:4px;box-shadow:none;border-color:rgba(255,255,255,.18);background:rgba(255,255,255,.08)}.nav-logout{color:#fff}.nav-logout:hover,.nav-logout:focus-visible{background:rgba(255,255,255,.14);color:#fff}
        }
        @media(max-width:480px){.main-nav.is-open{grid-template-columns:1fr}.main-nav .nav-primary,.user-menu{grid-column:auto}.brand small{display:none}}
        @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}.stat-card,.quick-actions a,.product-card{transition:none}}
    </style>
    @stack('styles')
</head>
<body>
    <header class="main-header">
        <div class="header-inner">
            <a class="brand" href="{{ auth()->user()->role->value === 'ADMIN' ? route('admin.dashboard') : route('waiter.orders') }}" aria-label="Mekatos, inicio"><span class="brand-mark">M</span><span><strong>Mekatos</strong><small>Comidas rápidas</small></span></a>
            <button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation"><span aria-hidden="true">☰</span><span>Menú</span></button>
            <nav class="main-nav" id="main-navigation" aria-label="Navegación principal">
                @php $currentRoute = request()->route()?->getName(); @endphp
                @if (auth()->user()->role->value === 'ADMIN')
                    <a href="{{ route('admin.dashboard') }}" {{ $currentRoute === 'admin.dashboard' ? 'aria-current=page' : '' }}>Inicio</a>
                    <a href="{{ route('admin.orders.index') }}" {{ str_starts_with($currentRoute ?? '', 'admin.orders.') && $currentRoute !== 'admin.orders.create' ? 'aria-current=page' : '' }}>Pedidos</a>
                    <a class="nav-primary" href="{{ route('admin.orders.create') }}" {{ $currentRoute === 'admin.orders.create' ? 'aria-current=page' : '' }}>+ Nuevo pedido</a>
                    <a href="{{ route('waiter.orders') }}" {{ $currentRoute === 'waiter.orders' ? 'aria-current=page' : '' }}>Pedidos mesero</a>
                    <a href="{{ route('admin.reports.daily') }}" {{ $currentRoute === 'admin.reports.daily' ? 'aria-current=page' : '' }}>Reportes</a>
                    <a href="{{ route('admin.categories.index') }}" {{ str_starts_with($currentRoute ?? '', 'admin.categories.') ? 'aria-current=page' : '' }}>Categorías</a>
                    <a href="{{ route('admin.products.index') }}" {{ str_starts_with($currentRoute ?? '', 'admin.products.') ? 'aria-current=page' : '' }}>Productos</a>
                    <a href="{{ route('admin.tables.index') }}" {{ str_starts_with($currentRoute ?? '', 'admin.tables.') ? 'aria-current=page' : '' }}>Mesas</a>
                    <a href="{{ route('admin.users.index') }}" {{ str_starts_with($currentRoute ?? '', 'admin.users.') ? 'aria-current=page' : '' }}>Usuarios</a>
                @else
                    <a href="{{ route('waiter.orders') }}" {{ $currentRoute === 'waiter.orders' ? 'aria-current=page' : '' }}>Pedidos</a>
                    <a class="nav-primary" href="{{ route('admin.orders.create') }}" {{ $currentRoute === 'admin.orders.create' ? 'aria-current=page' : '' }}>+ Nuevo pedido</a>
                @endif
                <div class="user-menu" id="user-menu">
                    <button type="button" class="user-chip" id="user-menu-toggle" aria-expanded="false" aria-controls="logout-menu"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->value === 'ADMIN' ? 'Administrador' : 'Mesero' }}</small></button>
                    <form method="POST" action="{{ route('logout') }}" class="logout-form" id="logout-menu">@csrf<button type="submit" class="nav-logout">Cerrar sesión</button></form>
                </div>
            </nav>
        </div>
    </header>
    <main>@yield('content')</main>
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded',()=>{
            const userMenu=document.getElementById('user-menu'),toggle=document.getElementById('user-menu-toggle');
            if(!userMenu||!toggle)return;
            toggle.addEventListener('click',()=>{const open=userMenu.classList.toggle('open');toggle.setAttribute('aria-expanded',open?'true':'false')});
            document.addEventListener('click',e=>{if(!userMenu.contains(e.target)){userMenu.classList.remove('open');toggle.setAttribute('aria-expanded','false')}});
        });
    </script>
    @stack('scripts')
</body>
</html>

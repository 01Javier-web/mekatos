<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#f3a6a6">
    <title>@yield('title', 'Mekatos')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        :root{--mk-ink:#171717;--mk-muted:#737373;--mk-border:#e6e6e3;--mk-soft:#fff3cf;--mk-card:#fffdf5}
        body{background:#fff3cf}
        .main-header{background:#f3a6a6;color:#3a1717;border-bottom-color:#e7aaaa;box-shadow:0 4px 18px rgba(80,30,30,.12);backdrop-filter:saturate(120%) blur(8px)}
        .brand{color:#3a1717}.brand-mark{background:#fff8df;color:#8f3b3b}.brand small{color:#714848}
        .main-nav a{color:#552525}.main-nav a:hover,.main-nav a:focus-visible{color:#3a1717;background:#efbcbc}
        .main-nav .nav-primary{background:#fff8df;color:#5a2525}.main-nav .nav-primary:hover{background:#fffdf0;color:#4b1d1d}
        .main-nav a[aria-current="page"]{color:#3a1717;background:#efbcbc;font-weight:800}
        .main-nav .nav-primary[aria-current="page"]{background:#fff8df;color:#5a2525}
        .user-chip{border-left-color:#d78f8f}.user-chip strong{color:#4a2020}.user-chip small{color:#754b4b}
        .nav-logout{color:#6d4141}.nav-logout:hover,.nav-logout:focus-visible{background:#efbcbc;color:#3a1717}
        .mobile-nav-toggle{border-color:#d78f8f;background:#f6b6b6;color:#4b1d1d}
        .main-nav{row-gap:5px}
        .mobile-nav-toggle{align-items:center;gap:5px}
        .stat-card strong{display:block}
        .stat-card small{display:block;margin-top:6px;color:#777;font-size:.74rem;line-height:1.35}
        @media(max-width:980px){.header-inner{gap:12px}.main-nav a{padding-inline:8px}.user-chip{padding-inline:7px}}
        @media(max-width:760px){
            .header-inner{min-height:60px;width:min(100% - 24px,1240px)}
            .mobile-nav-toggle{display:inline-flex;margin-left:auto}
            .main-nav{display:none;position:absolute;left:12px;right:12px;top:58px;padding:10px;background:#f3a6a6;border:1px solid #d78f8f;border-radius:14px;box-shadow:0 18px 45px rgba(80,30,30,.28)}
            .main-nav.is-open{display:grid;grid-template-columns:1fr 1fr;gap:5px}
            .main-nav a,.main-nav .nav-primary{padding:11px 12px;text-align:left}
            .main-nav .nav-primary{grid-column:1/-1}
            .user-chip{grid-column:1/-1;border-left:0;border-top:1px solid #d78f8f;margin:5px 0 0;padding:10px 5px 5px}
            .logout-form{grid-column:1/-1}.nav-logout{width:100%;text-align:left;padding:11px 12px}
        }
        @media(max-width:480px){.main-nav.is-open{grid-template-columns:1fr}.main-nav .nav-primary,.user-chip,.logout-form{grid-column:auto}.brand small{display:none}}
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
                <span class="user-chip"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role->value === 'ADMIN' ? 'Administrador' : 'Mesero' }}</small></span>
                <form method="POST" action="{{ route('logout') }}" class="logout-form">@csrf<button type="submit" class="nav-logout">Cerrar sesión</button></form>
            </nav>
        </div>
    </header>
    <main>@yield('content')</main>
    <script src="{{ asset('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>

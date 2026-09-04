<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Mekatos')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="main-header">
        <div class="header-inner">
            <a class="brand" href="{{ auth()->user()->role->value === 'ADMIN' ? route('admin.dashboard') : route('waiter.orders') }}"><span class="brand-mark">M</span><span><strong>Mekatos</strong><small>Comidas rápidas</small></span></a>
            <button class="mobile-nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation">☰ <span>Menú</span></button>
            <nav class="main-nav" id="main-navigation" aria-label="Navegación principal">
                @if (auth()->user()->role->value === 'ADMIN')
                    <a href="{{ route('admin.dashboard') }}">Inicio</a><a href="{{ route('admin.orders.index') }}">Pedidos</a><a class="nav-primary" href="{{ route('admin.orders.create') }}">+ Nuevo pedido</a><a href="{{ route('admin.categories.index') }}">Categorías</a><a href="{{ route('admin.products.index') }}">Productos</a><a href="{{ route('admin.tables.index') }}">Mesas</a><a href="{{ route('admin.users.index') }}">Usuarios</a>
                @else
                    <a href="{{ route('waiter.orders') }}">Pedidos</a><a class="nav-primary" href="{{ route('admin.orders.create') }}">+ Nuevo pedido</a>
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

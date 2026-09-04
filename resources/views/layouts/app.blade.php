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
            <a class="brand" href="{{ route('admin.orders.index') }}">Mekatos</a>
            <nav class="main-nav" aria-label="Navegación principal">
                <a href="{{ route('admin.orders.index') }}">Pedidos</a>
                <a href="{{ route('admin.categories.index') }}">Categorías</a>
                <a href="{{ route('admin.products.index') }}">Productos</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>

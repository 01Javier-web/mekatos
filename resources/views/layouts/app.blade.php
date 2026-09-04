<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        @yield('title', 'Restaurant')
    </title>

    {{-- CSS servido directamente desde /public --}}
    <link
        rel="stylesheet"
        href="{{ asset('css/app.css') }}"
    >
</head>

<body>

    <header>
        <h1>Restaurant Ordering</h1>
    </header>

    <main>
        @yield('content')
    </main>

    {{-- JavaScript tradicional, sin Vite --}}
    <script
        src="{{ asset('js/app.js') }}"
        defer
    ></script>

</body>
</html>
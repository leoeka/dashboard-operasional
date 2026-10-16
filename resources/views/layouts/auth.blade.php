<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-app.head />
</head>

<body class="min-h-screen">
    <main class="min-h-screen flex items-center justify-center p-4">
        @yield('content')
    </main>
</body>

</html>

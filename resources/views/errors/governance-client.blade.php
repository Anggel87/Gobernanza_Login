<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso no autorizado</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f7f7f8] text-[#1f1f23]">
    <main class="flex min-h-screen items-center justify-center px-6">
        <section class="w-full max-w-md rounded-lg bg-white p-8 text-center shadow-xl ring-1 ring-gray-200">
            <h1 class="text-2xl font-bold">Acceso no autorizado</h1>
            <p class="mt-4 text-gray-600">{{ $message }}</p>
        </section>
    </main>
</body>
</html>

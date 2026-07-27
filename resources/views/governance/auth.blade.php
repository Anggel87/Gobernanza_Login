<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gobernanza Auth</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-950">
    <main class="flex min-h-screen items-center justify-center px-4 py-8">
        <section class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl ring-1 ring-gray-200">
            <div class="mb-6">
                <p class="text-sm font-medium text-gray-500">{{ $clientApp->name }}</p>
                <h1 class="mt-1 text-2xl font-semibold">Acceso de gobernanza</h1>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('governance.auth.login') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="client_id" value="{{ $clientId }}">
                <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">

                <div>
                    <x-input-label for="login_email" value="Correo" />
                    <x-text-input id="login_email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required autofocus />
                </div>

                <div>
                    <x-input-label for="login_password" value="Contrasena" />
                    <x-text-input id="login_password" name="password" type="password" class="mt-1 block w-full" required />
                </div>

                <x-primary-button class="w-full justify-center">Continuar</x-primary-button>
            </form>
        </section>
    </main>
</body>
</html>

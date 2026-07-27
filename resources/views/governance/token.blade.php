<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Autenticacion completada</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 text-gray-950">
    <main class="flex min-h-screen items-center justify-center px-4">
        <section class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl ring-1 ring-gray-200">
            <h1 class="text-xl font-semibold">Autenticacion completada</h1>
            <p class="mt-2 text-sm text-gray-600">Puedes volver a la aplicacion.</p>
        </section>
    </main>

    <script>
        const payload = @json($payload);
        const redirectUri = @json($redirectUri);

        if (window.opener) {
            window.opener.postMessage({ type: 'governance_auth', data: payload }, '*');
            window.close();
        } else if (redirectUri) {
            const url = new URL(redirectUri);
            url.searchParams.set('token', payload.token);
            url.searchParams.set('token_type', payload.token_type);
            window.location.replace(url.toString());
        }
    </script>
</body>
</html>

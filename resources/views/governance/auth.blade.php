<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Gobernanza Auth</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white font-sans text-[#1f1f23] antialiased">
    <main class="min-h-screen lg:grid lg:grid-cols-2">
        <section class="relative hidden min-h-screen overflow-hidden lg:block">
            <img
                src="{{ asset('governance-assets/login-background.png') }}"
                alt=""
                class="absolute inset-0 h-full w-full object-cover"
            >
            <div class="absolute inset-0 bg-black/15"></div>
            <div class="absolute inset-x-0 bottom-0 h-2/5 bg-gradient-to-t from-black/60 to-transparent"></div>

            <div class="relative flex min-h-screen items-center px-20 text-white">
                <div class="max-w-2xl translate-y-28 xl:translate-y-36">
                    <p class="mb-6 inline-flex rounded-full bg-white/20 px-7 py-2 text-xs font-semibold uppercase tracking-[0.18em] backdrop-blur">
                        Excelencia académica
                    </p>
                    <h1 class="max-w-2xl text-5xl font-bold leading-tight">
                        El futuro del aprendizaje comienza aquí.
                    </h1>
                    <p class="mt-7 max-w-2xl text-xl leading-relaxed text-white/90">
                        Gestiona tus clases, estudiantes y progreso con la herramienta más intuitiva y potente del ecosistema educativo.
                    </p>
                </div>
            </div>
        </section>

        <section class="relative flex min-h-screen flex-col bg-white">
            <div class="relative h-[38vh] shrink-0 overflow-hidden lg:hidden">
                <img
                    src="{{ asset('governance-assets/login-background.png') }}"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover"
                >
                <div class="absolute inset-0 bg-black/20"></div>
                <img
                    src="{{ asset('governance-assets/logo-black.png') }}"
                    alt="Check Mate"
                    class="relative mx-auto mt-14 h-24 w-24 object-contain brightness-0 invert sm:h-28 sm:w-28"
                >
            </div>

            <div class="relative z-10 flex flex-1 flex-col lg:items-center lg:justify-center lg:px-16 xl:px-24">
                <div class="-mt-8 w-full flex-1 rounded-t-[2rem] bg-white px-7 pb-10 pt-9 sm:px-10 lg:mt-0 lg:max-w-md lg:flex-none lg:rounded-none lg:px-0 lg:py-0">
                    <div class="hidden text-center lg:block">
                        <img
                            src="{{ asset('governance-assets/logo-black.png') }}"
                            alt="Check Mate"
                            class="mx-auto h-28 w-28 object-contain"
                        >
                    </div>

                    <div class="text-left lg:mt-8 lg:text-center">
                        <h2 class="text-2xl font-bold leading-tight text-[#202024] lg:text-3xl">
                            Bienvenido de nuevo
                        </h2>
                        <p class="mt-3 text-base leading-relaxed text-[#6b6670] lg:text-lg">
                            Ingresa tus credenciales para acceder a tu panel de gestión.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('governance.auth.login') }}" class="mt-7 space-y-5 lg:mt-9 lg:space-y-6" x-data="{ showPassword: false }">
                        @csrf
                        <input type="hidden" name="client_id" value="{{ $clientId }}">
                        <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">

                        <div>
                            <label for="login_email" class="block text-xs font-bold uppercase tracking-wide text-[#595359]">
                                Correo Electrónico
                            </label>
                            <div class="mt-2.5 flex h-14 items-center rounded-xl bg-[#f2f2f4] px-4 ring-1 ring-[#e4e1e4] transition focus-within:bg-white focus-within:ring-2 focus-within:ring-black">
                                <svg class="h-5 w-5 shrink-0 text-[#5e595e]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-9Z" stroke="currentColor" stroke-width="2" />
                                    <path d="m5 8 7 5 7-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <input
                                    id="login_email"
                                    name="email"
                                    type="email"
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="nombre@institucion.edu"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 text-base font-medium text-[#222226] placeholder:text-[#9a95a0] focus:ring-0"
                                >
                            </div>
                        </div>

                        <div>
                            <label for="login_password" class="block text-xs font-bold uppercase tracking-wide text-[#595359]">
                                Contraseña
                            </label>
                            <div class="mt-2.5 flex h-14 items-center rounded-xl bg-[#f2f2f4] px-4 ring-1 ring-[#e4e1e4] transition focus-within:bg-white focus-within:ring-2 focus-within:ring-black">
                                <svg class="h-5 w-5 shrink-0 text-[#5e595e]" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <rect x="5" y="10" width="14" height="10" rx="2" stroke="currentColor" stroke-width="2" />
                                    <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    <path d="M12 14v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                </svg>
                                <input
                                    id="login_password"
                                    name="password"
                                    x-bind:type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="********"
                                    class="min-w-0 flex-1 border-0 bg-transparent px-3 text-base font-medium text-[#222226] placeholder:text-[#9a95a0] focus:ring-0"
                                >
                                <button type="button" x-on:click="showPassword = !showPassword" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-[#5e595e] transition hover:bg-black/5 focus:outline-none focus:ring-2 focus:ring-black" aria-label="Mostrar u ocultar contraseña">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                        <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <label class="hidden items-center gap-2.5 text-sm font-medium text-[#5b565b] lg:flex">
                            <input name="remember" type="checkbox" class="h-4 w-4 rounded border-[#d4cfd4] text-black shadow-sm focus:ring-black">
                            Mantener sesión iniciada
                        </label>

                        <button type="submit" class="flex h-14 w-full items-center justify-center gap-3 rounded-xl bg-black px-7 text-base font-semibold text-white transition hover:bg-[#202024] focus:outline-none focus:ring-4 focus:ring-black/20">
                            Iniciar Sesión
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <path d="m13 6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>

                        <div class="border-t border-[#e4e1e4] pt-5 lg:pt-6">
                            <div class="hidden gap-6 text-xs font-bold uppercase tracking-wide text-[#8a858a] lg:flex">
                                <a href="#" class="transition hover:text-black">Soporte</a>
                                <a href="#" class="transition hover:text-black">Privacidad</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <footer class="px-6 pb-10 pt-8 text-center text-[#a2a0a4] lg:hidden">
                <p class="text-sm font-bold uppercase tracking-[0.18em]">Checkmate &copy; 2026</p>
                <div class="mt-3 flex justify-center gap-8 text-sm font-medium">
                    <a href="#" class="transition hover:text-black">Términos</a>
                    <a href="#" class="transition hover:text-black">Privacidad</a>
                </div>
            </footer>
        </section>
    </main>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <script>
            (() => {
                const storedTheme = (() => {
                    try {
                        return window.localStorage.getItem('anl_theme');
                    } catch {
                        return null;
                    }
                })();
                const theme = ['light', 'dark', 'system'].includes(storedTheme) ? storedTheme : 'system';
                const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', isDark);
                document.documentElement.dataset.theme = theme;
                document.documentElement.dataset.resolvedTheme = isDark ? 'dark' : 'light';
                document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
            })();
        </script>

        <title>Login — ANL Dashboard V2</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-canvas text-ink transition-colors duration-200">
        <div class="relative min-h-screen flex items-center justify-center p-4">
            <div x-data="themeToggle" class="absolute top-4 right-4">
                <button type="button" @click="toggle"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-border bg-surface text-muted transition hover:border-cyan-400 hover:text-cyan-600 focus:outline-none focus:ring-2 focus:ring-cyan-500/40 dark:hover:text-cyan-400"
                    :aria-label="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'"
                    :title="dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap'">
                    <svg x-show="!dark" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                    <svg x-show="dark" x-cloak class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                </button>
            </div>

            <div class="w-full max-w-md">
                {{-- Brand --}}
                <div class="mb-6 text-center">
                    <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-slate-900 dark:bg-slate-800 shadow-lg shadow-slate-900/20 dark:shadow-black/40 mb-4">
                        <svg class="w-8 h-8 text-cyan-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-ink">ANL Dashboard V2</h1>
                    <p class="mt-1 text-sm text-muted">Operasional Logistik & Instalasi Sekolah</p>
                </div>

                <div class="bg-surface rounded-2xl shadow-lg shadow-slate-900/5 border border-border dark:shadow-black/30 p-8">
                    <!-- Session Status -->
                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <!-- Email Address -->
                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="admin@anl.com" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Password')" />

                            <x-text-input id="password" class="block mt-1 w-full"
                                            type="password"
                                            name="password"
                                            required autocomplete="current-password" placeholder="password" />

                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <!-- Remember Me -->
                        <div class="block mt-4">
                            <label for="remember_me" class="inline-flex items-center">
                                <input id="remember_me" type="checkbox" class="rounded border-slate-300 dark:border-slate-500 text-cyan-600 dark:text-cyan-400 shadow-sm focus:ring-cyan-500" name="remember">
                                <span class="ms-2 text-sm text-slate-600 dark:text-slate-300">{{ __('Remember me') }}</span>
                            </label>
                        </div>

                        <div class="mt-6">
                            <x-primary-button class="w-full justify-center py-3">
                                {{ __('Login') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>

                <p class="mt-5 text-center text-xs text-slate-400 dark:text-slate-400">
                    Akun dibuat melalui seeder atau manajemen user oleh administrator.
                </p>
            </div>
        </div>
    </body>
</html>
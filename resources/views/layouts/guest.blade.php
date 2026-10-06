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

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased bg-canvas transition-colors duration-200">
        <div class="relative min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-canvas px-4">
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

            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-cyan-600 dark:text-cyan-400" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-surface border border-border shadow-lg shadow-slate-900/5 dark:shadow-black/30 overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>

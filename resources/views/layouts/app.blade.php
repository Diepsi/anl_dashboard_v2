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

        <title>{{ $title ?? config('app.name', 'ANL Dashboard V2') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-canvas text-ink transition-colors duration-200">
        <div class="min-h-screen bg-canvas">
            @include('layouts.navigation')

            <!-- Page Content -->
            <main class="pt-16 md:pt-0 md:pl-64">
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
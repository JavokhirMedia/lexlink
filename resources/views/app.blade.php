<!DOCTYPE html>
<html lang="uz">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">

        {{-- Light only. Apply "Maxsus imkoniyatlar" (accessibility) classes before first paint. --}}
        <script>
            (function () {
                try {
                    var raw = window.localStorage.getItem('lexlink.a11y');
                    if (!raw) return;
                    var s = JSON.parse(raw) || {};
                    var c = document.documentElement.classList;
                    if (s.textSize === 112) c.add('a11y-text-112');
                    if (s.textSize === 125) c.add('a11y-text-125');
                    if (s.contrast) c.add('a11y-contrast');
                    if (s.grayscale) c.add('a11y-grayscale');
                } catch (e) {}
            })();
        </script>

        <style>
            html {
                background-color: #f5f7fa;
            }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap&subset=latin,latin-ext" rel="stylesheet">
        <meta name="theme-color" content="#0a5bd8">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'LexLink') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

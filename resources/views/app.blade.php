<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
        <link rel="icon" href="/icons/favicon-16.png" type="image/png" sizes="16x16">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#fbfaf7" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#14171a" media="(prefers-color-scheme: dark)">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Nexoma">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="msapplication-TileImage" content="/icons/mstile-150.png">
        <meta name="msapplication-TileColor" content="#fbfaf7">

        <script>
            (function () {
                var match = document.cookie.match(/(?:^|; )nexoma-theme=(light|dark)/);
                var theme = match ? match[1] : (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.dataset.theme = theme;
            })();
        </script>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Nexoma') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

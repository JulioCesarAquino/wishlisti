<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- The card apps like WhatsApp show for a shared link (they don't run JavaScript) --}}
        @isset($share)
            <meta name="description" content="{{ $share['description'] }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="{{ config('app.name') }}">
            <meta property="og:locale" content="pt_BR">
            <meta property="og:url" content="{{ $share['url'] }}">
            <meta property="og:title" content="{{ $share['title'] }}">
            <meta property="og:description" content="{{ $share['description'] }}">
            @if ($share['image'])
                <meta property="og:image" content="{{ $share['image'] }}">
                <meta property="og:image:type" content="image/jpeg">
                <meta property="og:image:width" content="{{ \App\Services\Events\EventSharePreviewService::WIDTH }}">
                <meta property="og:image:height" content="{{ \App\Services\Events\EventSharePreviewService::HEIGHT }}">
                <meta property="og:image:alt" content="{{ $share['title'] }}">
                <meta name="twitter:card" content="summary_large_image">
            @endif
        @endisset

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $share['title'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>

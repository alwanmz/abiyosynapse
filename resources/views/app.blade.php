<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Inline style to set the HTML background color, dark-mode aware to
         avoid a white flash for users with the dark theme active. --}}
    <style>
        html {
            background-color: #FFFFFF;
        }

        html.dark {
            background-color: #0D1420;
        }
    </style>

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" href="{{ $faviconUrl ?? '/favicon.svg' }}" type="image/svg+xml">
    <link rel="alternate icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="{{ $appleTouchIconUrl ?? '/apple-touch-icon.png' }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:400,500,600,700,800|noto-sans-jp:400,500,700|ibm-plex-mono:400,500" rel="stylesheet" />

    @viteReactRefresh
    @vite(['resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
    
    {{-- Memastikan semua request menggunakan HTTPS --}}
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">

    <title inertia>{{ config('app.name', 'Planly App') }}</title>

    {{-- --- UPDATE FAVICON MENGGUNAKAN LOGO.PNG --- --}}
    <link rel="icon" type="image/png" href="{{ asset('logo/logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo/logo.png') }}">

    <meta name="theme-color" content="#2563eb">
    
    {{-- Perbaikan peringatan deprecated --}}
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Laravel') }}">
    
    {{-- Manifest PWA --}}
    <link rel="manifest" href="{{ asset('build/manifest.webmanifest') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">

    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>
</html>
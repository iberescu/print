<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="RunMyPrint — custom business printing. Design online or upload your artwork.">
    <link rel="icon" type="image/svg+xml" href="/storage/brand/logo.svg">
    <title inertia>{{ config('app.name', 'RunMyPrint') }}</title>
    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @inertiaHead
    @php($gads = config('services.google_ads.tag_id') ? [
        'tag' => config('services.google_ads.tag_id'),
        'purchase' => config('services.google_ads.label_purchase'),
        'logo' => config('services.google_ads.label_logo'),
        'cart' => config('services.google_ads.label_cart'),
    ] : null)
    @if ($gads)
    {{-- Google Ads: conversion tracking + remarketing. Labels come from ads:setup. --}}
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gads['tag'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $gads['tag'] }}');
        window.__gads = {!! json_encode($gads) !!};
    </script>
    @endif
</head>
<body class="font-sans antialiased">
    @inertia
</body>
</html>

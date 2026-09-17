<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $seo['robots'] ?? 'noindex, nofollow' }}" data-inertia="robots">
    @isset($seo)
        <title data-inertia="title">{{ $seo['title'] }}</title>
        <meta name="description" content="{{ $seo['description'] }}" data-inertia="description">
        <meta property="og:title" content="{{ $seo['title'] }}" data-inertia="og:title">
        <meta property="og:description" content="{{ $seo['description'] }}" data-inertia="og:description">
        <meta property="og:url" content="{{ $seo['url'] }}" data-inertia="og:url">
        <meta property="og:type" content="website" data-inertia="og:type">
        <meta property="og:site_name" content="{{ config('storefront.name') }}" data-inertia="og:site_name">
        <meta property="og:locale" content="{{ config('storefront.locale') }}" data-inertia="og:locale">
        <link rel="canonical" href="{{ $seo['url'] }}" data-inertia="canonical">
        @if($seo['image'])
            <meta property="og:image" content="{{ $seo['image'] }}" data-inertia="og:image">
        @endif
    @endisset
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>

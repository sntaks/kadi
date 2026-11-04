@props([
    'title'       => null,
    'description' => null,
    'noindex'     => false,
    'page'        => 'home',
    // A share image in public/images for this page (plan Phase 11), e.g. og-sports.jpg; otherwise one by page.
    'og'          => null,
])

@php
    $siteName      = config('app.name', 'Kadi');
    $appUrl        = rtrim(config('app.url', 'https://kadi.online'), '/');
    $resolvedTitle = filled($title) ? $title : $siteName;
    $resolvedDesc  = filled($description)
        ? $description
        : 'Kenya\'s online competitive Kadi game anytime, anywhere';
    $ogImages = [
        'home'       => 'og-default.png',
        'rules'      => 'og-how-to.png',
        'game-guide' => 'og-games-guide.png',
        'terms'      => 'og-terms.png',
        'privacy'    => 'og-privacy.png',
    ];
    $ogImage = $appUrl . '/images/' . ($og ?? $ogImages[$page] ?? 'og-default.png');
    $canonical = $appUrl . request()->getPathInfo();
@endphp

<title>{{ $resolvedTitle }}</title>

<meta name="description" content="{{ $resolvedDesc }}">
@if ($noindex)
<meta name="robots" content="noindex,nofollow">
@else
<meta name="robots" content="index,follow,max-snippet:-1,max-image-preview:large,max-video-preview:-1">
@endif
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type"         content="website">
<meta property="og:locale"       content="en_KE">
<meta property="og:site_name"    content="{{ $siteName }}">
<meta property="og:title"        content="{{ $resolvedTitle }}">
<meta property="og:description"  content="{{ $resolvedDesc }}">
<meta property="og:url"          content="{{ $canonical }}">
<meta property="og:image"        content="{{ $ogImage }}">
<meta property="og:image:width"  content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt"    content="{{ $siteName }} — Kenya's Kadi Game">

<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="{{ $resolvedTitle }}">
<meta name="twitter:description" content="{{ $resolvedDesc }}">
<meta name="twitter:image"       content="{{ $ogImage }}">

<meta name="geo.region"    content="KE">
<meta name="geo.placename" content="Nairobi, Kenya">
<meta name="language"      content="English">

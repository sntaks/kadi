@props([
    'page'    => 'home',
    'noindex' => false,
])
@php
    $appUrl   = rtrim(config('app.url', 'https://kadi.online'), '/');
    $siteName = config('app.name', 'Kadi');
    $emit     = ! $noindex;
    $isHome   = $emit && $page === 'home';
@endphp

@if ($emit)
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "Organization",
    "name": "{{ $siteName }}",
    "url": "{{ $appUrl }}",
    "logo": {
        "@@type": "ImageObject",
        "url": "{{ $appUrl }}/favicon.svg",
        "width": 512,
        "height": 512
    },
    "contactPoint": {!! json_encode(array_filter([
        '@type' => 'ContactPoint',
        'contactType' => 'customer support',
        'telephone' => \App\Support\Contact::phoneInternational(),
        'email' => \App\Support\Contact::email(),
        'areaServed' => 'KE',
        'availableLanguage' => ['English', 'Swahili'],
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!},
    {{-- Profiles only: a WhatsApp chat link is not a profile page. --}}
    "sameAs": {!! json_encode(array_values(array_column(\Illuminate\Support\Arr::except(\App\Support\Contact::socials(), ['whatsapp']), 'url')), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
}
</script>
@endif

@if ($isHome)
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "WebSite",
    "name": "{{ $siteName }}",
    "alternateName": "Kadi Online",
    "url": "{{ $appUrl }}"
}
</script>
@endif

@if ($isHome)
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "WebApplication",
    "name": "{{ $siteName }} Online",
    "alternateName": ["Kadi", "Kadi Game", "Play Kadi"],
    "url": "{{ $appUrl }}",
    "applicationCategory": "GameApplication",
    "operatingSystem": "Any (web browser)",
    "description": "Play Kadi online, the Kenyan card game for 2 to 4 players.",
    "inLanguage": "en-KE",
    "offers": { "@@type": "Offer", "price": "0", "priceCurrency": "KES" }
}
</script>
@endif

@if ($breadcrumb = ['rules' => ['Kadi Rules', '/how-to'], 'game-guide' => ['Kadi Games & Prizes', '/games-guide'], 'faq' => ['Kadi FAQ', '/faq']][$page] ?? null)
@if ($emit)
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $appUrl],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $breadcrumb[0], 'item' => $appUrl.$breadcrumb[1]],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
@endif
@endif

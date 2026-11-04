<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="theme-color" content="{{ $themeColor ?? '#f5c542' }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
@if ($vapidPublicKey = config('webpush.vapid.public_key'))
<meta name="vapid-public-key" content="{{ $vapidPublicKey }}">
@endif
@auth
{{-- Opaque per-account marker (not the database id) so push.js can tell when a different account signs in on this device. --}}
<meta name="pwa-user" content="{{ substr(hash_hmac('sha256', (string) auth()->id(), (string) config('app.key')), 0, 16) }}">
@endauth

@if ($verification = config('services.analytics.google_site_verification'))
<meta name="google-site-verification" content="{{ $verification }}">
@endif

{{-- Performance: Preconnect to asset origins --}}
<link rel="preconnect" href="{{ config('app.url') }}">
<link rel="dns-prefetch" href="{{ config('app.url') }}">

<x-seo
    :title="$title ?? null"
    :description="$description ?? null"
    :noindex="$noindex ?? false"
    :page="$page ?? 'home'"
    :og="$og ?? null"
/>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="apple-touch-icon" sizes="180x180" href="/pwa-icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

{{-- PWA: installable web app (manifest, service worker registered from resources/js/pwa) --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Kadi">
<meta name="apple-mobile-web-app-status-bar-style" content="black">

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- The new site is dark only (plan §2): Flux's appearance script would follow a light system setting and drop .dark. --}}
@unless ($darkOnly ?? false)
@fluxAppearance
@endunless

@if ($gaId = config('services.analytics.ga_measurement_id'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ $gaId }}');
</script>
@endif

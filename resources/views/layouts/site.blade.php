{{--
    The one site shell (plan §2, Phase 4), for guests and players alike: top bar, notice strip, side menu
    (a drawer below 1024px), the page, footer and the phone bottom bar. Behind PLATFORM_UI_V2, every page
    reaches it through the old layout names (layouts.app, layouts.guest, layouts::auth), which pass `$shell`:

      app    signed-in pages: padded content column.
      page   public pages that bring their own full-width sections.
      panel  sign-in and gate forms in a centred panel. On a gate (consent, choose a name) the menu
             shows but is inert, so the player sees which site they are on and cannot skip the step.

    Pages keep their own content until their phase restyles it.
--}}
@php
    $nav = \App\Support\SiteNavigation::current();
    $shell ??= 'page';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        {{-- A meta tag needs a literal colour: the Night table `night` token. --}}
        @include('partials.head', ['themeColor' => '#0e0d0b', 'darkOnly' => true])
    </head>
    <body class="nt-site min-h-screen antialiased" data-shell="{{ $shell }}"
          x-data="{ menuOpen: false }"
          x-on:resize.window.debounce.150ms="if (innerWidth >= 1024) menuOpen = false">
        <a href="#main" class="nt-skip">Skip to content</a>

        <x-site.top-bar :nav="$nav" />
        {{-- Gates refuse every POST but their own, so the notices' actions would fail there. --}}
        @unless ($nav->isGated())
            <x-site.notices />
        @endunless

        <div class="nt-shell">
            <x-site.side-menu :nav="$nav" />

            <div class="nt-main">
                <main id="main" tabindex="-1" @class([
                    'nt-content',
                    'nt-content-app' => $shell === 'app',
                    'nt-content-panel' => $shell === 'panel',
                ])>
                    @if ($shell === 'panel')
                        <div class="nt-panel nt-gate-panel">{{ $slot }}</div>
                    @else
                        {{ $slot }}
                    @endif
                </main>

                <x-site.footer :nav="$nav" />
            </div>
        </div>

        <x-site.bottom-bar :nav="$nav" />

        @if (config('platform.sports'))
            {{-- Kept across wire:navigate so picks survive page changes; the sports pages fill it (Phase 9). --}}
            @persist('bet-slip')
                <div id="bet-slip" data-bet-slip></div>
            @endpersist
        @endif

        <x-structured-data :page="$page ?? ($shell === 'page' ? 'home' : 'dashboard')" :noindex="$noindex ?? $shell !== 'page'" />
        @if (auth()->check() && ! $nav->isGated())
            <livewire:phone-required />
            <livewire:wallet.deposit-sheet />
            <x-pwa.install-dialog />
        @endif
        @fluxScripts
    </body>
</html>

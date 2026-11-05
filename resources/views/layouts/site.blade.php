{{--
    The one site shell (plan §2, Phase 4), for guests and players alike: top bar, notice strip, side menu
    (a drawer below 1024px), the page, footer and the phone bottom bar. Every page uses it, passing `$shell`
    (sign-in and gate pages through <x-auth.page>):

      app    signed-in pages: padded content column.
      page   public pages that bring their own full-width sections.
      panel  sign-in and gate forms in a centred panel. On a gate (consent, choose a name) the menu
             shows but is inert, so the player sees which site they are on and cannot skip the step.

    `rail` (sports pages and the landing, while sports is on) keeps the bet slip beside the page from 1240px up;
    everywhere else the slip is a sheet behind the bottom bar's Bet slip button or the floating pill (Phase 9).
--}}
@php
    $nav = \App\Support\SiteNavigation::current();
    $shell ??= 'page';
    $sports = \App\Support\SportsPages::on();
    $rail = $sports && ($rail ?? false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        {{-- A meta tag needs a literal colour: the Night table `night` token. --}}
        @include('partials.head', ['themeColor' => '#0e0d0b', 'darkOnly' => true])
        @if ($sports)
            <meta name="kadi-max-selections" content="{{ (int) config('sportsbook.limits.max_selections') }}">
        @endif
    </head>
    <body @class(['nt-site min-h-screen antialiased', 'nt-has-rail' => $rail]) data-shell="{{ $shell }}"
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

        @guest
            {{-- Not on the sign-in pages themselves: they show the same forms in the page. --}}
            @unless ($shell === 'panel')
                <x-site.signin-sheet />
            @endunless
        @endguest

        @if ($sports && ! $nav->isGated())
            {{-- Kept across wire:navigate, so a placement being checked keeps its state between pages. --}}
            @persist('bet-slip')
                <livewire:sports.bet-slip />
            @endpersist
            @if ($rail)
                <livewire:sports.match-panel />
            @endif
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

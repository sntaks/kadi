{{-- The sport sidebar: the sports, then this sport's leagues by country (<x-sports.league-picker>) with real counts.
     It sits beside the list when the page has room (container query in sports.css). Narrower, the sports stay a
     row and the leagues open from one button in a sheet. The sheet is teleported to <body>: .nt-sport-wrap is a
     size container, which would trap a fixed sheet inside it. `active` is the competition slug on a competition page. --}}
@props(['sport', 'sports' => [], 'competitions' => [], 'active' => null, 'tab' => null])
@use('App\Sportsbook\Support\CompetitionRegion')
@php
    $current = collect($competitions)->firstWhere('slug', $active);
    $sportName = collect($sports)->firstWhere('slug', $sport)['name'] ?? \App\Sportsbook\Support\SportProfile::find($sport)?->name ?? 'Sports';
    $total = array_sum(array_column($competitions, 'count'));
    $myBets = \App\Support\SiteNavigation::current()->sportsBetsLink(needsAccount: true);
@endphp
<nav {{ $attributes->class('nt-sportnav') }} aria-label="Sports and leagues">
    @if (count($sports) > 1 || $myBets)
        <div class="nt-sportnav-sports">
            @if (count($sports) > 1)
                @foreach ($sports as $item)
                    <a class="nt-sportnav-sport" href="{{ \App\Sportsbook\Catalogue\SportsLobby::sportUrl($item['slug']) }}" wire:navigate
                       @if ($item['slug'] === $sport) aria-current="{{ $active === null ? 'page' : 'true' }}" @endif>
                        <x-site.icon :name="$item['icon']" /><span>{{ $item['name'] }}</span><small class="nt-num">{{ $item['count'] }}</small>
                    </a>
                @endforeach
            @endif
            {{-- The player's bets as slips (Activity › Sports). Guests get the sign-in sheet; the URL works without JS. --}}
            @if ($myBets)
                <a class="nt-sportnav-sport nt-sportnav-bets" href="{{ $myBets['href'] }}" data-test="sports-my-bets"
                   @if ($myBets['needsAccount']) data-needs-account @else wire:navigate @endif>
                    <x-site.icon name="slip" /><span>My bets</span>
                </a>
            @endif
        </div>
    @endif

    {{-- Wide: the leagues in the sidebar. --}}
    <x-sports.league-picker class="nt-sportnav-list" :$sport :$competitions :$active :$tab id="lp-side" />

    {{-- Narrow: one button for the leagues, opening the sheet. --}}
    @if ($competitions !== [])
        <div class="nt-sportnav-pick" x-data>
            <button type="button" class="nt-lp-trigger" aria-haspopup="dialog" x-on:click="$dispatch('open-sheet', 'league-picker')">
                @if ($current)
                    <x-ui.flag :flag="CompetitionRegion::flag($current['region'])" :size="22" />
                    <span class="nt-lp-two"><b>{{ CompetitionRegion::shortName($current['name'], $current['region']) }}</b><em>{{ CompetitionRegion::name($current['region']) }}</em></span>
                @else
                    <x-site.icon name="trophy" />
                    <span class="nt-lp-two"><b>All {{ $sportName }} leagues</b><em>{{ count($competitions) }} leagues · {{ $total }} matches</em></span>
                @endif
                <x-site.icon name="chev" />
                <span class="sr-only">Choose a league</span>
            </button>

            <template x-teleport="body">
                <x-ui.sheet name="league-picker" :title="$sportName.' leagues'" class="nt-lp-sheet">
                    <x-sports.league-picker :$sport :$competitions :$active :$tab id="lp-sheet" />
                </x-ui.sheet>
            </template>
        </div>
    @endif
</nav>

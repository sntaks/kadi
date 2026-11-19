{{-- A sport's leagues to pick from: search, Highlights · Today, Popular, then countries and regions (round flags,
     real counts). Used in the sport sidebar on wide pages and in the "league-picker" sheet on narrow ones. Countries
     are <details>, so they open without JavaScript; Alpine (resources/js/sports/league-picker.js) adds the search.
     A country with one league links straight to it; the current league's country starts open. --}}
@props(['sport', 'competitions' => [], 'active' => null, 'tab' => null, 'id' => 'lp'])
@use('App\Sportsbook\Support\CompetitionRegion')
@use('Illuminate\Support\Str')
@php
    $home = \App\Sportsbook\Catalogue\SportsLobby::sportUrl($sport);
    $onSport = $active === null;
    $current = fn (bool $on) => $on ? 'aria-current=page' : '';
    $popular = array_values(array_filter($competitions, fn (array $c) => $c['popular']));
    $groups = CompetitionRegion::group($competitions);
    $search = fn (array $c) => Str::lower(Str::ascii($c['name'].' '.CompetitionRegion::name($c['region']).' '.$c['aliases']));
@endphp
<div {{ $attributes->class('nt-lp') }} x-data="leaguePicker">
    @if ($competitions !== [])
        <label class="nt-lp-search" x-cloak>
            <x-site.icon name="search" />
            <input type="search" id="{{ $id }}-search" x-ref="search" x-model="q" placeholder="Search leagues"
                   aria-label="Search leagues or countries" aria-controls="{{ $id }}-results" autocomplete="off" enterkeyhint="search"
                   x-on:keydown.escape="if (q !== '') { q = ''; $event.stopPropagation() }">
        </label>
    @endif

    <div class="nt-lp-browse" x-show="term === ''">
        <a class="nt-lp-row" href="{{ $home }}" wire:navigate {{ $current($onSport && ($tab === null || $tab === 'highlights')) }}><x-site.icon name="ball" /><span>Highlights</span></a>
        <a class="nt-lp-row" href="{{ $home }}?tab=today" wire:navigate {{ $current($onSport && $tab === 'today') }}><x-site.icon name="clock" /><span>Today</span></a>

        @if ($popular !== [])
            <h3>Popular</h3>
            @foreach ($popular as $competition)
                <a class="nt-lp-row" href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate {{ $current($active === $competition['slug']) }}>
                    <x-ui.flag :flag="CompetitionRegion::flag($competition['region'])" />
                    <span>{{ CompetitionRegion::shortName($competition['name'], $competition['region']) }}</span><small class="nt-num">{{ $competition['count'] }}</small>
                </a>
            @endforeach
        @endif

        @if ($groups !== [])
            <h3>Countries</h3>
            @foreach ($groups as $group)
                @if (count($group['competitions']) === 1)
                    @php($competition = $group['competitions'][0])
                    <a class="nt-lp-row" href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate {{ $current($active === $competition['slug']) }}>
                        <x-ui.flag :flag="$group['flag']" />
                        <span class="nt-lp-two"><b>{{ $group['name'] }}</b><em>{{ CompetitionRegion::shortName($competition['name'], $group['code']) }}</em></span>
                        <small class="nt-num">{{ $competition['count'] }}</small>
                    </a>
                @else
                    @php($here = in_array($active, array_column($group['competitions'], 'slug'), true))
                    <details class="nt-lp-country" @if ($here) open @endif>
                        <summary class="nt-lp-row">
                            <x-ui.flag :flag="$group['flag']" />
                            <span>{{ $group['name'] }}</span><small class="nt-num">{{ $group['count'] }}</small>
                            <x-site.icon name="chev" />
                        </summary>
                        <div class="nt-lp-leagues">
                            @foreach ($group['competitions'] as $competition)
                                <a class="nt-lp-row" href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate {{ $current($active === $competition['slug']) }}>
                                    <span>{{ CompetitionRegion::shortName($competition['name'], $group['code']) }}</span><small class="nt-num">{{ $competition['count'] }}</small>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endif
            @endforeach
        @endif
    </div>

    @if ($competitions !== [])
        <div class="nt-lp-results" id="{{ $id }}-results" x-ref="results" x-show="term !== ''" x-cloak aria-live="polite">
            @foreach ($competitions as $competition)
                <a class="nt-lp-row" href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate
                   data-search="{{ $search($competition) }}" x-show="matches($el)" {{ $current($active === $competition['slug']) }}>
                    <x-ui.flag :flag="CompetitionRegion::flag($competition['region'])" />
                    <span class="nt-lp-two"><b>{{ CompetitionRegion::shortName($competition['name'], $competition['region']) }}</b><em>{{ CompetitionRegion::name($competition['region']) }}</em></span>
                    <small class="nt-num">{{ $competition['count'] }}</small>
                </a>
            @endforeach
            <p class="nt-lp-none" x-show="none">No league or country matches “<span x-text="q.trim()"></span>”.</p>
        </div>
    @endif
</div>

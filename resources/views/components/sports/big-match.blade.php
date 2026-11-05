{{--
    Today's big match on the landing (plan §2): a countdown to kick-off, its 1X2 and a link to all its markets, then
    the other kick-offs today, or the next ones when nothing else plays today. The match is picked by a rule
    (SportsLobby::bigMatch), never by a popularity claim; every price is a real, tappable price.
--}}
@props(['fixture', 'also', 'alsoToday' => true])
@php
    $market = $fixture->matchResultMarket;
    $tz = config('sportsbook.timezone');
    $today = $fixture->kicks_off_at->timezone($tz)->isSameDay(now($tz));
    $kickoff = $fixture->kicks_off_at->getTimestamp();
@endphp
{{-- Keyed by fixture: when a re-render picks another match, its countdown starts afresh instead of timing the old one. --}}
<section class="nt-panel nt-bigmatch" wire:key="bigmatch-{{ $fixture->id }}" aria-labelledby="bigmatch-title" data-match="{{ $fixture->name() }}" data-market="{{ $market?->name() }}">
    <div>
        <h2 id="bigmatch-title">{{ $today ? "Today's big match" : 'Next big match' }}</h2>
        <p class="nt-meta">{{ $fixture->competition->name }} · <span class="nt-num">{{ $fixture->kickoffLabel() }}</span></p>
        <p class="nt-versus"><b>{{ $fixture->home_team }}</b><span class="nt-vs" aria-label="versus">v</span><b>{{ $fixture->away_team }}</b></p>
        <p class="nt-kickoff" x-data="kickoffCountdown({{ $kickoff }}, @js(\App\Sportsbook\Support\KickoffCountdown::labels()))" data-test="kickoff-countdown">
            <time datetime="{{ $fixture->kicks_off_at->toIso8601String() }}" x-text="text">{{ \App\Sportsbook\Support\KickoffCountdown::label($fixture->kicks_off_at) }}</time>
        </p>
        <div class="nt-trio">
            @foreach ($market?->selections ?? [] as $selection)
                <x-sports.odds :$selection :$fixture :open="$market->isOpen()" wire:key="big-{{ $selection->id }}" />
            @endforeach
        </div>
        <p class="nt-more">
            <span class="nt-num">{{ $fixture->markets_count }} {{ \Illuminate\Support\Str::plural('market', $fixture->markets_count) }}</span>
            <a href="{{ route('sports.match', $fixture) }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))">All markets</a>
        </p>
    </div>

    @if ($also->isNotEmpty())
        <div class="nt-also" data-test="{{ $alsoToday ? 'also-today' : 'coming-up' }}">
            <h3>{{ $alsoToday ? 'Also kicking off today' : 'Coming up' }}</h3>
            @foreach ($also as $other)
                <a href="{{ route('sports.match', $other) }}" x-on:click="$openMatch($event, {{ $other->id }}, @js($other->name()))">
                    <span class="nt-num">{{ $alsoToday ? $other->kicks_off_at->timezone($tz)->format('H:i') : $other->kickoffLabel() }}</span>
                    <span>{{ $other->name() }}</span>
                    <span class="nt-num">{{ $other->matchResultMarket?->selections->map(fn ($s) => $s->price() !== null ? number_format((float) $s->price(), 2) : '–')->implode('  ') }}</span>
                </a>
            @endforeach
        </div>
    @endif
</section>

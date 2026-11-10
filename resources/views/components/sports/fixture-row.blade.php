{{-- One match in a list: kick-off, teams (opening the match panel), the prices of the chosen market and "+N". --}}
@props(['fixture', 'marketKey' => 'h2h', 'columns' => 3])
@php
    $market = \App\Sportsbook\Catalogue\FixtureListing::listMarket($fixture, $marketKey);
    $selections = $market?->selections ?? collect();
    // One slot per column. A 1X2 column set places prices by outcome, so a basketball Winner (no draw) in a mixed
    // list (search) shows 1 – 2, never its away price under X.
    $slots = $columns === 3 && $market?->type === \App\Sportsbook\Enums\MarketType::MatchResult
        ? collect(['home', 'draw', 'away'])->map(fn (string $outcome) => $selections->firstWhere('outcome', $outcome))
        : $selections->take($columns)->values()->pad($columns, null);
    $more = max(($fixture->markets_count ?? 0) - ($market ? 1 : 0), 0);
    $url = route('sports.match', $fixture);
    $kickoff = $fixture->kicks_off_at->timezone(config('sportsbook.timezone'));
@endphp
<div class="nt-fx" @if ($columns === 2) data-two @endif data-match="{{ $fixture->name() }}" data-market="{{ $market?->name() }}" wire:key="fixture-{{ $fixture->id }}">
    <span class="nt-fx-when nt-num"><time datetime="{{ $fixture->kicks_off_at->toIso8601String() }}">{{ $kickoff->format('H:i') }}</time></span>
    <a class="nt-fx-teams" href="{{ $url }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))">
        <span>{{ $fixture->home_team }}</span><span>{{ $fixture->away_team }}</span>
    </a>
    @foreach ($slots as $selection)
        @if ($selection)
            <x-sports.odds :$selection :$fixture :open="$market->isOpen()" wire:key="selection-{{ $selection->id }}" />
        @else
            <span class="nt-odds nt-odds-none" aria-hidden="true"><b>–</b></span>
        @endif
    @endforeach
    @if ($columns === 2)<span class="nt-fx-line nt-num">{{ $market?->line !== null && $marketKey === 'totals' ? rtrim(rtrim($market->line, '0'), '.') : '' }}</span>@endif
    <a class="nt-fx-more nt-num" href="{{ $url }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))"
       aria-label="{{ $more }} more {{ \Illuminate\Support\Str::plural('market', $more) }} for {{ $fixture->name() }}">+{{ $more }}</a>
</div>

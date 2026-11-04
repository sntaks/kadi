{{-- One match in a list: kick-off, teams (opening the match panel), the prices of the chosen market and "+N". --}}
@props(['fixture', 'marketKey' => 'h2h', 'columns' => 3])
@php
    $market = $fixture->markets->firstWhere('key', $marketKey);
    $selections = $market?->selections ?? collect();
    $more = max(($fixture->markets_count ?? 0) - ($market ? 1 : 0), 0);
    $url = route('sports.match', $fixture);
    $kickoff = $fixture->kicks_off_at->timezone(config('sportsbook.timezone'));
@endphp
<div class="nt-fx" @if ($columns === 2) data-two @endif data-match="{{ $fixture->name() }}" data-market="{{ $market?->name() }}" wire:key="fixture-{{ $fixture->id }}">
    <span class="nt-fx-when nt-num"><time datetime="{{ $fixture->kicks_off_at->toIso8601String() }}">{{ $kickoff->format('H:i') }}</time></span>
    <a class="nt-fx-teams" href="{{ $url }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))">
        <span>{{ $fixture->home_team }}</span><span>{{ $fixture->away_team }}</span>
    </a>
    @forelse ($selections as $selection)
        <x-sports.odds :$selection :$fixture :open="$market->isOpen()" wire:key="selection-{{ $selection->id }}" />
    @empty
        @for ($i = 0; $i < $columns; $i++)
            <span class="nt-odds nt-odds-none" aria-hidden="true"><b>–</b></span>
        @endfor
    @endforelse
    @if ($columns === 2)<span aria-hidden="true"></span>@endif
    <a class="nt-fx-more nt-num" href="{{ $url }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))"
       aria-label="{{ $more }} more {{ \Illuminate\Support\Str::plural('market', $more) }} for {{ $fixture->name() }}">+{{ $more }}</a>
</div>

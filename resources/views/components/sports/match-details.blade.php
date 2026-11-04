{{-- A match's header and markets, on the match page and in the match panel. --}}
@props(['fixture', 'marketsByTab', 'tab', 'inPanel' => false])
@php $kickoff = $fixture->kicks_off_at->timezone(config('sportsbook.timezone')); @endphp
<div class="nt-match-details">
    <header class="nt-panel nt-match-head">
        @if ($inPanel)
            <p class="nt-crumb">{{ $fixture->competition->name }}</p>
        @else
            <a class="nt-crumb" href="{{ route('sports.competition', $fixture->competition) }}" wire:navigate>{{ $fixture->competition->name }}</a>
        @endif
        <{{ $inPanel ? 'h2' : 'h1' }} class="nt-versus-title">
            <span>{{ $fixture->home_team }}</span><span class="nt-vs" aria-label="versus">v</span><span>{{ $fixture->away_team }}</span>
        </{{ $inPanel ? 'h2' : 'h1' }}>
        <p class="nt-num"><time datetime="{{ $fixture->kicks_off_at->toIso8601String() }}">{{ $fixture->kickoffLabel() }}</time> EAT</p>
        @if ($fixture->hasKickedOff())
            <p class="nt-slip-error" role="status">This match has started, so betting on it is closed.</p>
        @endif
    </header>

    @if ($marketsByTab->isEmpty())
        <div class="nt-panel nt-empty px-[22px]"><b>No markets open</b>Prices for this match aren't available right now.</div>
    @else
        <div class="nt-tabs" role="tablist" aria-label="Market groups">
            @foreach ($marketsByTab->keys() as $key)
                <button type="button" role="tab" class="nt-tab" wire:click="$set('tab', '{{ $key }}')"
                        aria-selected="{{ $tab === $key ? 'true' : 'false' }}">{{ \App\Sportsbook\Catalogue\MarketTabs::label($key) }}</button>
            @endforeach
        </div>

        <div class="nt-mkts">
            @foreach ($marketsByTab->get($tab, $marketsByTab->first()) as $market)
                <x-sports.market-block :$market :$fixture />
            @endforeach
        </div>
    @endif
</div>

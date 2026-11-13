{{-- /predictions/bets (App\Livewire\Predict\MyBets): Open and Settled prediction bets, and a bet still being checked. --}}
<div class="nt-predict nt-view-in">
    <x-ui.page-head title="My bets" crumb="Predictions" description="Your prediction bets. Open bets on pool markets show estimates until the market closes.">
        <x-slot:actions>
            <x-ui.button variant="ghost" size="sm" :href="route('predictions')" wire:navigate>All markets</x-ui.button>
        </x-slot:actions>
    </x-ui.page-head>

    @if ($pending)
        <section class="nt-pm-pending" aria-labelledby="pm-pending">
            <x-ui.section-head id="pm-pending" title="Checking this bet" sub="We didn't get a clear answer when you placed it" />
            <livewire:predict.slip :market-id="$pending['market_id']" show-question :key="'pending-'.$pending['market_id']" />{{-- never the reference: keys are rendered in the page --}}
        </section>
    @endif

    <x-ui.tabs label="Bets">
        <x-ui.tab :active="$tab === 'open'" wire:click="setTab('open')">Open</x-ui.tab>
        <x-ui.tab :active="$tab === 'settled'" wire:click="setTab('settled')">Settled</x-ui.tab>
    </x-ui.tabs>

    @if (! $available)
        <div class="nt-empty nt-panel"><b>Your bets can't be loaded right now</b>Please try again in a minute.</div>
    @elseif ($bets === [])
        <div class="nt-empty nt-panel">
            <b>{{ $tab === 'open' ? 'No open bets' : 'No settled bets yet' }}</b>
            {{ $tab === 'open' ? 'Bets you place show here until their market is decided.' : 'Bets show here once their market is decided.' }}
            <div class="mt-3"><x-ui.button size="sm" :href="route('predictions')" wire:navigate>Browse markets</x-ui.button></div>
        </div>
    @else
        <ul class="nt-pm-bets">
            @foreach ($bets as $bet)
                @php
                    $sideLabel = $labels[$bet->marketId][$bet->side] ?? ucfirst($bet->side);
                    $placed = $bet->placedAt?->timezone(config('sportsbook.timezone', 'Africa/Nairobi'));
                @endphp
                <li class="nt-pm-bet" wire:key="pm-bet-{{ $bet->id }}">
                    <div class="nt-pm-bet-main">
                        <a href="{{ route('predictions.market', $bet->marketId) }}" class="nt-pm-bet-q"
                           x-on:click="$openMarket($event, {{ $bet->marketId }}, @js($bet->question ?? 'Market'))">{{ $bet->question ?? 'Market #'.$bet->marketId }}</a>
                        <p class="nt-pm-bet-meta">
                            <span class="nt-pm-bet-side">{{ $sideLabel }}</span>
                            <span class="nt-num">{{ \App\Predict\Format::kes($bet->stake) }}</span>
                            @if ($placed)<span class="nt-num">{{ $placed->format('j M Y, H:i') }}</span>@endif
                        </p>
                    </div>
                    <div class="nt-pm-bet-result">
                        @if ($bet->isOpen())
                            @if ($bet->potentialPayout !== null)
                                <b class="nt-num">{{ \App\Predict\Format::kes($bet->potentialPayout) }}</b>
                                <small class="nt-num">{{ collect([$bet->multiplier ? \App\Predict\Format::multiplier($bet->multiplier) : null, $bet->oddsLabel()])->filter()->implode(' · ') }}</small>
                            @else
                                <small>{{ $bet->oddsLabel() }}</small>
                            @endif
                        @elseif ($bet->status === 'won')
                            <b class="nt-num nt-pm-won">Won {{ \App\Predict\Format::kes($bet->payout ?? 0) }}</b>
                            @if ($bet->multiplier)<small class="nt-num">at {{ \App\Predict\Format::multiplier($bet->multiplier) }}</small>@endif
                        @elseif ($bet->isVoid())
                            <b>Stake refunded</b>
                            <small>Market voided</small>
                        @else
                            <b class="nt-pm-lost">Lost</b>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="nt-pm-more-bar">
            <span class="nt-num" aria-live="polite">Showing {{ number_format(count($bets)) }} of {{ number_format($total) }}</span>
            @if ($more)
                <x-ui.button variant="ghost" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore" data-predict-load-more>
                    <span wire:loading.remove wire:target="loadMore">Load more bets</span>
                    <span wire:loading wire:target="loadMore">Loading…</span>
                </x-ui.button>
            @endif
        </div>
    @endif
</div>

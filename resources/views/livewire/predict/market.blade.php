<div class="nt-predict nt-view-in" data-predict-page data-predict-live wire:poll.{{ (int) config('predict.feed.page_poll_seconds', 25) }}s.visible="sync">
    @php
        $reason = $market->unavailableReason($now);
        $closes = $market->bettingClosesAt?->timezone($market->timezone);
        $left = \App\Predict\Format::timeLeft($market->bettingClosesAt, $now);
        $category = $market->category ? \App\Predict\Markets::categoryLabel($market->category) : null;
        $odds = $market->odds;
        $openText = $left ? 'Ends in '.$left : 'Open';
    @endphp

    <nav class="nt-pm-crumbs" aria-label="Breadcrumb">
        <a href="{{ route('predictions') }}" wire:navigate>Predictions</a>
        @if ($category)
            <span aria-hidden="true">›</span>
            <a href="{{ route('predictions', ['category' => $market->category]) }}" wire:navigate>{{ $category }}</a>
        @endif
    </nav>

    <header class="nt-pm-hero" data-predict-market="{{ $market->id }}">
        <x-predict.image :url="$market->imageUrl" size="lg" />
        <div class="min-w-0">
            <p class="nt-pm-kicker">
                @if ($market->eventTitle)<span>{{ $market->eventTitle }}</span>@endif
                <span data-predict-status data-open-text="{{ $openText }}" @class(['nt-pm-reason' => $reason !== null])>{{ $reason ?? $openText }}</span>
            </p>
            <h1>{{ $market->question }}</h1>
        </div>
    </header>

    @if ($stale)
        <p class="nt-pm-stale" role="status">Prices may be out of date. Any bet uses the price shown when you place it.</p>
    @endif

    <div class="nt-pm-layout">
        <div class="nt-pm-main">
            <section class="nt-panel nt-pm-chart-panel" aria-labelledby="pm-chart" data-predict-market="{{ $market->id }}">
                <h2 id="pm-chart" class="sr-only">Chance over time</h2>
                <x-predict.chances :$market />
                @if ($history !== null)
                    <x-predict.chart :points="$history" :current="$odds?->yesProbability" :$now :market-id="$market->id" :timezone="$market->timezone" wire:key="pm-chart-{{ $market->id }}-{{ $seenVersion }}" />
                @else
                    <div class="nt-pm-chart-wait" wire:init="loadChart" aria-hidden="true"></div>
                @endif
                <x-predict.attribution :odds="$odds" />
            </section>

            <x-predict.stats :$market />

            <x-predict.rules :$market :$criteria />
        </div>

        <aside class="nt-pm-aside">
            <livewire:predict.slip :market-id="$market->id" :initial-side="$side" show-question :key="'slip-'.$market->id" />

            @if ($related !== [])
                <section class="nt-pm-related" aria-labelledby="pm-related">
                    <h2 id="pm-related" class="nt-pm-h2">More markets</h2>
                    <ul>
                        @foreach ($related as $other)
                            <li data-predict-market="{{ $other->id }}" wire:key="pm-rel-{{ $other->id }}">
                                <a href="{{ route('predictions.market', $other->id) }}" x-on:click="$openMarket($event, {{ $other->id }}, @js($other->question), @js($other->imageUrl))">
                                    <x-predict.image :url="$other->imageUrl" />
                                    <span>{{ $other->question }}</span>
                                    <b class="nt-num" data-predict-chance="yes">{{ \App\Predict\Format::percent($other->odds?->yesProbability) ?? '–' }}</b>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</div>

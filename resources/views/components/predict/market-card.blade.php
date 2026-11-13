{{-- A market in the lobby: picture, question (two lines), the chance of Yes, both sides, and a footer with what is
     known about it (pool volume and bets, or fixed odds) and the time left to bet. Cards in a row share a height. --}}
@props(['market', 'now'])
@php
    $reason = $market->unavailableReason($now);
    $left = \App\Predict\Format::timeLeft($market->bettingClosesAt, $now);
    $odds = $market->odds;
@endphp
<article {{ $attributes->class('nt-pm-card') }} data-predict-market="{{ $market->id }}">
    <div class="nt-pm-card-head">
        <x-predict.image :url="$market->imageUrl" />
        <a href="{{ route('predictions.market', $market->id) }}" class="nt-pm-q" x-on:click="$openMarket($event, {{ $market->id }}, @js($market->question), @js($market->imageUrl))">{{ $market->question }}</a>
        <x-predict.gauge :probability="$odds?->yesProbability" />
    </div>
    <div class="nt-pm-sides">
        <x-predict.outcome-button :$market side="yes" :$now />
        <x-predict.outcome-button :$market side="no" :$now />
    </div>
    <footer class="nt-pm-card-foot">
        @if (($odds?->volume ?? 0) > 0 && ! $odds->isReference)
            <span class="nt-num">{{ \App\Predict\Format::kesShort($odds->volume) }} vol</span>
            @if ($odds->betsCount)<span class="nt-num">{{ number_format($odds->betsCount) }} {{ \Illuminate\Support\Str::plural('bet', $odds->betsCount) }}</span>@endif
        @else
            <span>{{ $market->oddsKind() }}</span>
        @endif
        <span class="nt-pm-left nt-num" data-predict-status data-open-text="{{ $left ?? 'Closed' }}" @class(['nt-pm-reason' => $reason !== null])>{{ $reason ?? $left }}</span>
    </footer>
    <x-predict.attribution :odds="$odds" />
</article>

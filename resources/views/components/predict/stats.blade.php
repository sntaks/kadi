{{-- Real figures only: pool volume and bets (never on fixed-odds markets, which have none), the odds kind, the cut-off. --}}
@props(['market'])
@php
    $odds = $market->odds;
    $closes = $market->bettingClosesAt?->timezone($market->timezone);
@endphp
<dl {{ $attributes->class('nt-pm-stats') }}>
    @if ($odds && ! $odds->isReference)
        <div><dt>Volume</dt><dd class="nt-num">{{ \App\Predict\Format::kesShort($odds->volume ?? 0) }}</dd></div>
        @if ($odds->betsCount !== null)<div><dt>Bets</dt><dd class="nt-num">{{ number_format($odds->betsCount) }}</dd></div>@endif
    @endif
    <div><dt>Odds</dt><dd>{{ $market->oddsKind() }}</dd></div>
    @if ($closes)<div><dt>Betting closes</dt><dd class="nt-num">{{ $closes->format('D j M Y, H:i') }}</dd></div>@endif
</dl>

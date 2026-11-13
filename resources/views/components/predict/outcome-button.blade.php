{{--
    One side of a market: its label and what KES 1 pays ("Yes 1.53x"). Never a binding price: on a market the
    figures are indicative (fixed by a quote) or estimated (pool). `as`:
      link    (lobby) goes to the market page, which preselects the side;
      button  (market page) picks the side for the bet slip. A guest gets a link that opens the sign-in sheet.
    Off when the market does not take bets or the side has no multiplier (the API cannot price it now, or it would
    pay less than the stake). resources/js/predict/live.js keeps the figure and the state current.
--}}
@props(['market', 'side', 'now', 'as' => 'link', 'canBet' => false])
@php
    $odds = $market->odds;
    $label = $odds?->label($side) ?? ucfirst($side);
    $multiplier = $odds?->multiplier($side);
    $open = $market->sideAvailable($side, $now);
    $figure = $multiplier !== null ? \App\Predict\Format::multiplier($multiplier) : '–';
    $name = $label.': '.($open ? 'pays '.$figure.' '.strtolower($market->oddsKind()) : ($multiplier === null ? 'not on offer' : 'betting closed'));
    $guest = auth()->guest();
    $classes = 'nt-pm-side nt-pm-side-'.$side;
    $data = ['data-predict-side' => $side, 'aria-label' => $name];
    $asLink = $as === 'link' || ($guest && $open);
@endphp
@if ($asLink)
    <a href="{{ route('predictions.market', ['market' => $market->id] + ($as === 'link' && $open ? ['side' => $side] : [])) }}"
       @if ($as !== 'link') data-needs-account x-on:click="$dispatch('predict-panel-handoff')" @endif
       @if ($as === 'link') x-on:click="$openMarket($event, {{ $market->id }}, @js($market->question), @js($market->imageUrl))" @endif
       @unless ($open) aria-disabled="true" @endunless
       {{ $attributes->class([$classes, 'is-off' => ! $open])->merge($data) }}>
@else
    <button type="button" @disabled(! $open || ! $canBet) data-can-bet="{{ $canBet ? '1' : '0' }}"
            @if ($open && $canBet) x-on:click="$dispatch('predict-pick', { market: {{ $market->id }}, side: '{{ $side }}' })" @endif
            {{ $attributes->class($classes)->merge($data) }}>
@endif
        <span class="nt-pm-side-label">{{ $label }}</span>
        <span class="nt-pm-side-mult nt-num" data-predict-mult>{{ $figure }}</span>
@if ($asLink)
    </a>
@else
    </button>
@endif

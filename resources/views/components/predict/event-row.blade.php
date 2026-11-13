{{-- One market inside an event card: its question, the chance of Yes and what Yes pays. --}}
@props(['market', 'now', 'label' => null])
<li class="nt-pm-row" data-predict-market="{{ $market->id }}" wire:key="pm-row-{{ $market->id }}">
    <a href="{{ route('predictions.market', $market->id) }}" class="nt-pm-row-q" title="{{ $market->question }}" x-on:click="$openMarket($event, {{ $market->id }}, @js($market->question), @js($market->imageUrl))">{{ $label ?? $market->question }}</a>
    <b class="nt-num nt-pm-row-chance" data-predict-chance="yes">{{ \App\Predict\Format::percent($market->odds?->yesProbability) ?? '–' }}</b>
    <x-predict.outcome-button :$market side="yes" :$now class="nt-pm-side-chip" />
</li>

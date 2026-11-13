{{-- The chance of each side, Yes large in gold. live.js keeps both current. --}}
@props(['market'])
@php($odds = $market->odds)
<div {{ $attributes->class('nt-pm-chances') }}>
    <div><small>{{ $odds?->label('yes') ?? 'Yes' }}</small><b class="nt-num nt-pm-chance-yes" data-predict-chance="yes">{{ \App\Predict\Format::percent($odds?->yesProbability) ?? '–' }}</b></div>
    <div><small>{{ $odds?->label('no') ?? 'No' }}</small><b class="nt-num" data-predict-chance="no">{{ \App\Predict\Format::percent($odds?->noProbability) ?? '–' }}</b></div>
</div>

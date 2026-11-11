{{--
    A price you can tap. States: `selected` (in the slip, gold), `movement` 'up' | 'down' (price changed
    since you last saw it), `suspended` (market closed: disabled, no price). With no price yet it is
    unavailable the same way, so it can never be added without one. `label` is the outcome
    (1, X, 2, Over…); `pick` describes it for screen readers ("Arsenal to win").
--}}
@props(['label', 'price' => null, 'pick' => null, 'selected' => false, 'movement' => null, 'suspended' => false, 'showLabel' => true])
@php
    $priceText = $price !== null && $price !== '' ? number_format((float) $price, 2) : null;
    $unavailable = $suspended || $priceText === null;
    $moved = $movement === 'up' ? ', price up' : ($movement === 'down' ? ', price down' : '');
    $name = $unavailable
        ? ($pick ?? $label).': '.($suspended ? 'suspended' : 'no price yet')
        : ($pick ?? $label).' at '.$priceText.$moved.($selected ? ', in your bet slip' : '');
@endphp
<button type="button" aria-pressed="{{ $selected && ! $unavailable ? 'true' : 'false' }}" aria-label="{{ $name }}" @disabled($unavailable) {{ $attributes->class('nt-odds')->merge(['data-activity' => 'odds']) }}>
    @if ($showLabel)<small aria-hidden="true">{{ $label }}</small>@endif
    <b aria-hidden="true">
        @if ($unavailable)
            –
        @else
            {{ $priceText }}@if ($movement === 'up')<span class="nt-move nt-move-up">▲</span>@elseif ($movement === 'down')<span class="nt-move nt-move-down">▼</span>@endif
        @endif
    </b>
</button>

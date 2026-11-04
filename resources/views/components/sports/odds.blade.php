{{--
    A sports price you can tap: adds the selection to the bet slip (the Alpine `betslip` store), or takes it off.
    Closed markets, kicked-off matches and missing prices are disabled, so a pick can never be added without a price.
    The labels the slip shows before the server answers come from data-pick-name and the [data-market] and
    [data-match] around the button ($pickLabels).
--}}
@props(['selection', 'fixture', 'open' => true, 'label' => null, 'showLabel' => true])
@php
    $price = $selection->price();
    $bettable = $open && $price !== null && ! $fixture->hasKickedOff();
    $movement = $bettable ? $selection->recentMovement() : null;
    $label ??= $selection->shortLabel();
    $priceText = $price !== null ? number_format((float) $price, 2) : null;
@endphp
@if ($bettable)
    <button type="button" {{ $attributes->class('nt-odds') }}
            aria-pressed="false"
            x-bind:aria-pressed="$store.betslip.has({{ $selection->id }}) ? 'true' : 'false'"
            x-on:click="$store.betslip.toggle({{ $fixture->id }}, {{ $selection->id }}, @js($price), $pickLabels)"
            data-pick-name="{{ $selection->name }}"
            aria-label="{{ $selection->name }} at {{ $priceText }}{{ $movement === 'up' ? ', price up' : ($movement === 'down' ? ', price down' : '') }}">
        @if ($showLabel)<small aria-hidden="true">{{ $label }}</small>@endif
        <b aria-hidden="true">{{ $priceText }}@if ($movement === 'up')<span class="nt-move nt-move-up">▲</span>@elseif ($movement === 'down')<span class="nt-move nt-move-down">▼</span>@endif</b>
    </button>
@else
    <button type="button" disabled {{ $attributes->class('nt-odds') }} aria-label="{{ $selection->name }}: betting closed">
        @if ($showLabel)<small aria-hidden="true">{{ $label }}</small>@endif
        <b aria-hidden="true">–</b>
    </button>
@endif

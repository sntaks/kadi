{{-- Wherever a Polymarket price or chart shows (licence requirement): the API's text, or our fallback. Pool and LMSR
     markets, imported or not, use our own prices and carry none. --}}
@props(['odds'])
@if ($odds?->isReference && $odds->attribution)
    <p {{ $attributes->class('nt-pm-attr') }}>{{ $odds->attribution }}</p>
@endif

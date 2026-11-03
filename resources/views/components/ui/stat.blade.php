{{-- A labelled figure. `gold` for money the player cares about. --}}
@props(['label', 'value', 'gold' => false])
<div {{ $attributes->class(['nt-stat', 'nt-stat-gold' => $gold]) }}><b class="nt-num">{{ $value }}</b><span>{{ $label }}</span></div>

{{-- The chance of Yes as a half-ring with the whole percent inside. live.js moves it (data-predict-gauge). --}}
@props(['probability' => null])
@php($percent = $probability !== null ? (int) round(min(1, max(0, $probability)) * 100) : null)
<span {{ $attributes->class('nt-pm-gauge') }} data-predict-gauge role="img" aria-label="Chance of Yes: {{ $percent !== null ? $percent.'%' : 'not shown' }}">
    <svg viewBox="0 0 48 28" aria-hidden="true">
        <path d="M4 26a20 20 0 0 1 40 0" pathLength="100" class="nt-pm-gauge-track" />
        <path d="M4 26a20 20 0 0 1 40 0" pathLength="100" class="nt-pm-gauge-fill" stroke-dasharray="{{ $percent ?? 0 }} 100" data-predict-gauge-fill />
    </svg>
    <b class="nt-num" data-predict-chance="yes">{{ $percent !== null ? $percent.'%' : '–' }}</b>
    <small>chance</small>
</span>

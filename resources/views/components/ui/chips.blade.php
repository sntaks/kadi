{{-- A scrolling row of chips. `label` names the group for screen readers. --}}
@props(['label'])
<div role="group" aria-label="{{ $label }}" {{ $attributes->class('nt-chips') }}>{{ $slot }}</div>

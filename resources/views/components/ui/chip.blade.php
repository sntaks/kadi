{{-- Filter chip (a toggle): `pressed` sets aria-pressed. Put several in <x-ui.chips>. --}}
@props(['pressed' => false])
<button type="button" aria-pressed="{{ $pressed ? 'true' : 'false' }}" {{ $attributes->class('nt-chip') }}>{{ $slot }}</button>

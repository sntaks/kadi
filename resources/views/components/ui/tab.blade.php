{{-- One tab. With an href it is a navigation link (aria-current); without, a role="tab" button (aria-selected). --}}
@props(['active' => false, 'href' => null])
@if ($href)
    <a href="{{ $href }}" @if ($active) aria-current="page" @endif {{ $attributes->class('nt-tab') }}>{{ $slot }}</a>
@else
    <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" tabindex="{{ $active ? '0' : '-1' }}" {{ $attributes->class('nt-tab') }}>{{ $slot }}</button>
@endif

{{-- Felt panel with a gold hairline. `as` picks the element; `padded` adds the standard inset. --}}
@props(['as' => 'section', 'padded' => false])
<{{ $as }} {{ $attributes->class(['nt-panel', 'p-5 sm:p-[22px]' => $padded]) }}>{{ $slot }}</{{ $as }}>

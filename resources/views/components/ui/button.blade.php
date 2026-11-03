{{--
    Night table button. A link when given an href, otherwise a <button>. variant: gold | ghost | quiet;
    size: sm | md | lg. A disabled link loses its href and is announced as disabled.
--}}
@props(['variant' => 'gold', 'size' => 'md', 'href' => null, 'block' => false, 'type' => 'button', 'disabled' => false])
@php
    $classes = 'nt-btn nt-btn-'.$variant
        .($size === 'md' ? '' : ' nt-btn-'.$size)
        .($block ? ' nt-btn-block' : '');
@endphp
@if ($href && $disabled)
    <a role="link" aria-disabled="true" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@elseif ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif

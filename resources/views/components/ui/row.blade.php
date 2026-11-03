{{-- List row: icon, title, meta line, trailing value or action. A link when given an href. --}}
@props(['title', 'meta' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class('nt-row') }}>
    <span class="nt-row-icon" aria-hidden="true">{{ $icon ?? '' }}</span>
    <span class="min-w-0"><b>{{ $title }}</b>@if ($meta)<span class="nt-meta nt-num">{{ $meta }}</span>@endif</span>
    <span class="nt-trail nt-num">{{ $trail ?? '' }}</span>
</{{ $tag }}>

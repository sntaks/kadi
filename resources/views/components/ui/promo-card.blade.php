{{-- Side-menu promo card with tilted artwork. --}}
@props(['title', 'text' => null, 'href' => '#', 'image' => null])
<a href="{{ $href }}" {{ $attributes->class('nt-promo') }}>
    <b>{{ $title }}</b>
    @if ($text)<span>{{ $text }}</span>@endif
    @if ($image)<img src="{{ $image }}" alt="" loading="lazy">@endif
</a>

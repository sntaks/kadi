{{-- A market's picture, straight from the source's CDN with no referrer (plan decision 9). The tile behind it shows
     when there is none or it fails to load. --}}
@props(['url' => null, 'size' => 'sm'])
<span {{ $attributes->class(['nt-pm-img', 'nt-pm-img-lg' => $size === 'lg']) }} aria-hidden="true">
    <x-site.icon name="predict" />
    @if ($url)
        <img src="{{ $url }}" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" x-data x-on:error="$el.remove()">
    @endif
</span>

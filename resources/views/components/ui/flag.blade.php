{{-- A round country flag (public/images/flags, from flag-icons) or, for a region without one, a globe. Decorative:
     the country's name is always beside it. Codes and paths come from App\Sportsbook\Support\CompetitionRegion. --}}
@props(['flag' => null, 'size' => 20])
<span {{ $attributes->class('nt-flag') }} style="--nt-flag: {{ (int) $size }}px" aria-hidden="true">
    @if ($flag)
        <img src="{{ asset($flag) }}" alt="" width="{{ (int) $size }}" height="{{ (int) $size }}" loading="lazy" decoding="async">
    @else
        <x-site.icon name="globe" />
    @endif
</span>

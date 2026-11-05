{{--
    One config slide of the landing hero (App\Games\HeroSlide): background, art layers, then the copy. Every layer
    carries data-layer and a stagger index (--i) for its entrance (resources/css/hero.css). Images use a <source> for
    768px and wider only, so phones, which never see these slides, never download them.
--}}
@props(['slide'])
@php
    $art = collect($slide->art);
    $bg = $art->firstWhere('role', 'bg');
    $cards = $art->where('role', 'card')->values();
    $objects = $art->where('role', 'object')->values();
    $pixel = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    $sizes = ['bg' => '(min-width: 1500px) 60vw, 100vw', 'object' => '(min-width: 1500px) 400px, 34vw', 'card' => '200px'];
    $needsAccount = ($slide->extra['needsAccount'] ?? false) && ! auth()->check();
@endphp
<div class="nt-hero-card" data-kind="{{ $slide->kind }}">
    @if ($bg)
        <div class="nt-hero-bg" data-layer="bg" aria-hidden="true">
            <picture>
                <source media="(min-width: 768px)" srcset="{{ $bg['srcset'] }}" sizes="{{ $sizes['bg'] }}">
                <img src="{{ $pixel }}" alt="" width="{{ $bg['w'] }}" height="{{ $bg['h'] }}" loading="lazy" decoding="async">
            </picture>
        </div>
    @endif

    @if ($slide->transition === 'slices')
        <div class="nt-hero-slices" aria-hidden="true">
            @for ($s = 0; $s < 5; $s++)
                <i data-slice style="--s: {{ $s }}"></i>
            @endfor
        </div>
    @endif

    <div class="nt-hero-copy">
        @if ($slide->eyebrow)
            <p class="nt-hero-eyebrow" data-layer="eyebrow" style="--i: 0">{{ $slide->eyebrow }}</p>
        @endif
        <h2 class="nt-hero-title" data-layer="title" data-split style="--i: 1">{{ $slide->title }}</h2>
        @if ($slide->copy)
            <p class="nt-hero-text" data-layer="copy" style="--i: 2">{{ $slide->copy }}</p>
        @endif

        @if ($slide->kind === 'install')
            <x-ui.button size="lg" data-hero-install data-layer="cta" style="--i: 3">Install the app</x-ui.button>
        @elseif ($slide->ctaUrl)
            @if ($needsAccount)
                <x-ui.button size="lg" :href="$slide->ctaUrl" data-needs-account data-layer="cta" style="--i: 3">{{ $slide->ctaLabel }}</x-ui.button>
            @else
                <x-ui.button size="lg" :href="$slide->ctaUrl" wire:navigate data-layer="cta" style="--i: 3">{{ $slide->ctaLabel }}</x-ui.button>
            @endif
        @endif
    </div>

    @if ($cards->isNotEmpty())
        <div class="nt-hero-fan" style="--n: {{ $cards->count() }}">
            @foreach ($cards as $card)
                @php $tag = $card['href'] ? 'a' : 'span'; @endphp
                <{{ $tag }} class="nt-hero-fan-card" data-layer="card" style="--i: {{ $loop->index }}"
                    @if ($card['href']) href="{{ $card['href'] }}" wire:navigate aria-label="{{ $card['alt'] }}" @endif>
                    <picture>
                        <source media="(min-width: 768px)" srcset="{{ $card['srcset'] }}" sizes="{{ $sizes['card'] }}">
                        <img src="{{ $pixel }}" alt="" width="{{ $card['w'] }}" height="{{ $card['h'] }}" loading="lazy" decoding="async">
                    </picture>
                </{{ $tag }}>
            @endforeach
        </div>
    @endif

    @foreach ($objects as $object)
        <div class="nt-hero-object" data-layer="object" style="--i: {{ $loop->index }}" aria-hidden="true">
            <picture>
                <source media="(min-width: 768px)" srcset="{{ $object['srcset'] }}" sizes="{{ $sizes['object'] }}">
                <img src="{{ $pixel }}" alt="" width="{{ $object['w'] }}" height="{{ $object['h'] }}" loading="lazy" decoding="async">
            </picture>
        </div>
    @endforeach
</div>

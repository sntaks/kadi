{{--
    One config slide of the landing hero (App\Games\HeroSlide): background, art layers, then the copy; tiers slides add
    a prize table and market slides a live market card. Every layer
    carries data-layer and a stagger index (--i) for its entrance (resources/css/hero.css). Images start as a 1x1
    placeholder with data-src/data-srcset: the slider script loads them only when their slide is next or showing.
--}}
@props(['slide'])
@php
    $art = collect($slide->art);
    $bg = $art->firstWhere('role', 'bg');
    $cards = $art->where('role', 'card')->values();
    $objects = $art->where('role', 'object')->values();
    $pixel = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';
    $sizes = ['bg' => '(min-width: 1500px) 60vw, 100vw', 'object' => '(min-width: 768px) 34vw, 200px', 'card' => '(min-width: 768px) 200px, 90px'];
    $needsAccount = ($slide->extra['needsAccount'] ?? false) && ! auth()->check();
@endphp
<div class="nt-hero-card" data-kind="{{ $slide->kind }}">
    @if ($bg)
        <div class="nt-hero-bg" data-layer="bg" aria-hidden="true">
            <img src="{{ $pixel }}" data-src="{{ $bg['src'] }}" data-srcset="{{ $bg['srcset'] }}" data-sizes="{{ $sizes['bg'] }}"
                     alt="" width="{{ $bg['w'] }}" height="{{ $bg['h'] }}" decoding="async">
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

        @if ($slide->ctaUrl)
            @if ($needsAccount)
                <x-ui.button size="lg" :href="$slide->ctaUrl" data-needs-account data-layer="cta" style="--i: 3">{{ $slide->ctaLabel }}</x-ui.button>
            @else
                <x-ui.button size="lg" :href="$slide->ctaUrl" wire:navigate data-layer="cta" style="--i: 3">{{ $slide->ctaLabel }}</x-ui.button>
            @endif
        @endif
    </div>

    {{-- Slot jackpot tiers: the Kadi prize pool's table (lobby.css .nt-prize), one medal colour and icon per tier. --}}
    @if ($slide->kind === 'tiers')
        <aside class="nt-prize nt-hero-tiers" data-layer="card" style="--i: 0" aria-label="{{ $slide->label }}" data-prize-pool>
            <dl class="nt-prize-rows" x-data="prizeCount">
                @foreach ($slide->extra['tiers'] as $tier)
                    <div @class(['nt-prize-row', 'is-top' => $loop->first]) data-tone="{{ $tier['tone'] ?? 'gold' }}">
                        <dt><span class="nt-prize-medal" aria-hidden="true">@svg('hugeicons-'.($tier['icon'] ?? 'award-01'))</span>{{ $tier['name'] }}</dt>
                        <dd class="nt-num" data-count-to="{{ $tier['amount'] }}" data-count-final="KSh {{ number_format($tier['amount']) }}">KSh {{ number_format($tier['amount']) }}</dd>
                    </div>
                @endforeach
            </dl>
        </aside>
    @endif

    {{-- A Local prediction market (App\Games\HeroSlides::markets()), live like the lobby's cards. --}}
    {{-- An event's market (one per candidate) lists every choice: its chance and what Yes pays, all on the slide. --}}
    @if ($slide->kind === 'market')
        <div class="nt-hero-market" data-layer="card" style="--i: 0" data-predict-live>
            @if ($choices = $slide->extra['choices'] ?? null)
                @php $labels = \App\Predict\Markets::outcomeLabels($choices); @endphp
                <article class="nt-pm-card nt-hero-choices" data-predict-event aria-label="{{ $slide->title }}">
                    <ul class="nt-pm-rows">
                        @foreach ($choices as $choice)
                            <x-predict.event-row :market="$choice" :now="now()" :label="$labels[$choice->id] ?? null" />
                        @endforeach
                    </ul>
                    <footer class="nt-pm-card-foot">
                        <span class="nt-num">{{ count($choices) }} choices</span>
                        <span class="nt-pm-left nt-num">{{ \App\Predict\Format::timeLeft($choices[0]->bettingClosesAt, now()) ?? 'Closed' }}</span>
                    </footer>
                </article>
            @else
                <x-predict.market-card :market="$slide->extra['market']" :now="now()" />
            @endif
        </div>
    @endif

    @if ($cards->isNotEmpty())
        <div class="nt-hero-fan" style="--n: {{ $cards->count() }}">
            @foreach ($cards as $card)
                @php $tag = $card['href'] ? 'a' : 'span'; @endphp
                <{{ $tag }} class="nt-hero-fan-card" data-layer="card" style="--i: {{ $loop->index }}"
                    @if ($card['href']) href="{{ $card['href'] }}" wire:navigate aria-label="{{ $card['alt'] }}" @endif>
                    <img src="{{ $pixel }}" data-src="{{ $card['src'] }}" data-srcset="{{ $card['srcset'] }}" data-sizes="{{ $sizes['card'] }}"
                     alt="" width="{{ $card['w'] }}" height="{{ $card['h'] }}" decoding="async">
                </{{ $tag }}>
            @endforeach
        </div>
    @endif

    @foreach ($objects as $object)
        <div class="nt-hero-object" data-layer="object" style="--i: {{ $loop->index }}" aria-hidden="true">
            <img src="{{ $pixel }}" data-src="{{ $object['src'] }}" data-srcset="{{ $object['srcset'] }}" data-sizes="{{ $sizes['object'] }}"
                     alt="" width="{{ $object['w'] }}" height="{{ $object['h'] }}" decoding="async">
        </div>
    @endforeach
</div>

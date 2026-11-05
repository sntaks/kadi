{{--
    The landing hero carousel (768px and wider; plans/landing-hero-slider-implementation-plan.md). Slides come from
    App\Games\HeroSlides; the first is always the Kadi table, with today's big match beside it while sports is on.

    Server state only: slide 1 active, the others hidden and inert, the controls hidden. The heroSlider script
    (resources/js/site/hero-slider.js) owns data-state, hidden and inert from then on: the root and each slide are
    wire:ignore.self and the controls wire:ignore, so a Livewire re-render of the landing keeps the slide the player is
    on while still updating what is inside it. Without JavaScript only slide 1 shows.
--}}
@props(['slides', 'seats' => null, 'bigMatch' => null, 'also' => null, 'alsoToday' => true])
@php $count = $slides->count(); @endphp
<section class="nt-hero" data-hero data-count="{{ $count }}" aria-roledescription="carousel" aria-label="Kadi Online highlights"
         @if ($count > 1) x-data="heroSlider" wire:ignore.self @endif>
    <div class="nt-hero-stage" id="nt-hero-stage" data-hero-stage>
        @foreach ($slides as $slide)
            <article class="nt-hero-slide" id="nt-hero-{{ $slide->key }}" data-slide="{{ $slide->key }}" data-kind="{{ $slide->kind }}"
                     data-transition="{{ $slide->transition }}" data-duration="{{ $slide->duration }}" data-label="{{ $slide->label }}"
                     data-state="{{ $loop->first ? 'active' : 'idle' }}" @if ($slide->kind === 'install') data-requires-install @endif
                     role="group" aria-roledescription="slide" aria-label="{{ $loop->iteration }} of {{ $count }}: {{ $slide->label }}"
                     wire:key="hero-{{ $slide->key }}" wire:ignore.self @unless ($loop->first) hidden inert @endunless>
                @if ($slide->kind === 'lead')
                    @if ($bigMatch)
                        <div class="nt-lead nt-lead-two nt-hero-lead">
                            <div class="nt-hero-lead-card" data-layer="card" style="--i: 0"><x-lobby.kadi-table :seats="$seats" /></div>
                            <div class="nt-hero-lead-card" data-layer="card" style="--i: 1"><x-sports.big-match :fixture="$bigMatch" :$also :also-today="$alsoToday" /></div>
                        </div>
                    @else
                        <div class="nt-hero-lead nt-hero-lead-solo">
                            <div class="nt-hero-lead-card" data-layer="card" style="--i: 0"><x-lobby.kadi-table :seats="$seats" /></div>
                        </div>
                    @endif
                @else
                    <x-lobby.hero-slide :$slide />
                @endif
            </article>
        @endforeach
    </div>

    @if ($count > 1)
        <div class="nt-hero-chrome" data-hero-chrome hidden wire:ignore>
            <button type="button" class="nt-hero-arrow" data-hero-prev aria-controls="nt-hero-stage" aria-label="Previous slide">
                <x-site.icon name="chev" class="rotate-90" />
            </button>
            <button type="button" class="nt-hero-arrow" data-hero-next aria-controls="nt-hero-stage" aria-label="Next slide">
                <x-site.icon name="chev" class="-rotate-90" />
            </button>

            <div class="nt-hero-nav">
                <button type="button" class="nt-hero-toggle" data-hero-toggle aria-pressed="false" aria-label="Pause slides">
                    <span aria-hidden="true"></span>
                </button>
                <div class="nt-hero-tabs" data-hero-tabs>
                    @foreach ($slides as $slide)
                        <button type="button" class="nt-hero-tab" data-hero-go="{{ $loop->index }}" aria-controls="nt-hero-{{ $slide->key }}"
                                @if ($loop->first) aria-current="true" @endif>
                            <span class="nt-num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="nt-hero-tab-label">{{ $slide->label }}</span>
                            <i class="nt-hero-bar" data-hero-bar aria-hidden="true"></i>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        <p class="sr-only" aria-live="polite" data-hero-live></p>
    @endif
</section>

{{--
    The landing hero carousel, at every width. Slides come from App\Games\HeroSlides; the first is always the Kadi
    table, then today's big match while sports is on. Below 768px the big match (data-below) leaves the rotation and
    shows under the carousel. It never gets `hidden` (Tailwind's base [hidden] rule is !important, so nothing could
    show it again): hero.css hides it from 768px until the script arms.

    No arrows, tabs, dots or pause button at any width: the slides move on by themselves, pause while the pointer or
    focus is on them, and players can swipe (or use the arrow keys inside a slide). Server state only: slide 1 active,
    the others hidden and inert. The heroSlider script (resources/js/site/hero-slider.js) owns data-state, hidden and
    inert from then on: the root and each slide are wire:ignore.self, so a Livewire re-render of the landing keeps the
    slide the player is on while still updating what is inside it. Without JavaScript only slide 1 shows.
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
                     @if ($below = $slide->kind === 'match') data-below @endif
                     wire:key="hero-{{ $slide->key }}" wire:ignore.self @unless ($loop->first || $below) hidden inert @endunless>
                @if ($slide->kind === 'kadi')
                    <div class="nt-hero-feature" data-layer="card" style="--i: 0"><x-lobby.kadi-table :seats="$seats" /></div>
                @elseif ($slide->kind === 'match')
                    <div class="nt-hero-feature" data-layer="card" style="--i: 0"><x-sports.big-match :fixture="$bigMatch" :$also :also-today="$alsoToday" /></div>
                @else
                    <x-lobby.hero-slide :$slide />
                @endif
            </article>
        @endforeach
    </div>

    @if ($count > 1)
        {{-- No visible controls: this invisible element carries the autoplay timer (its animationend moves on). --}}
        <i class="nt-hero-clock" data-hero-clock aria-hidden="true"></i>
        <p class="sr-only" aria-live="polite" data-hero-live></p>
    @endif
</section>

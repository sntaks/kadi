{{--
    A game tile: 2:3 art, play button on hover/focus, optional favourite heart and badge.
    Pass a GameTitle as `title` (art, name and badges come from the catalogue), or name/image directly.
    `favourite`: null hides the heart, true/false sets its state. `toggle` wires the heart to the Livewire
    page's toggleFavourite() (App\Livewire\Concerns\TogglesFavourites).
--}}
@props(['title' => null, 'name' => null, 'image' => null, 'href' => null, 'sub' => null, 'badge' => null, 'favourite' => null, 'toggle' => false])
@php
    /** @var \App\Games\GameTitle|null $title */
    $name ??= $title?->name;
    $image ??= $title?->thumbnail;
    $badge ??= $title?->hasBadge('jackpot') ? 'Jackpot' : null;
    $sub ??= $title?->kindLabel();
@endphp
<div {{ $attributes->class('nt-tile') }}>
    <div class="nt-art">
        @if ($badge)<x-ui.pill tone="gold" class="nt-badge">{{ $badge }}</x-ui.pill>@endif
        @if ($image)
            <img src="{{ $image }}" alt="" loading="lazy" width="320" height="480">
        @else
            <span class="nt-art-fallback" aria-hidden="true">{{ $name }}</span>
        @endif
        @if ($href)
            <a href="{{ $href }}" class="nt-play" aria-label="Play {{ $name }}"><span><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4v16l13-8z"/></svg></span></a>
        @endif
        @if ($favourite !== null)
            <button type="button" class="nt-fav" aria-pressed="{{ $favourite ? 'true' : 'false' }}" aria-label="Favourite {{ $name }}" @if ($title) data-fav="{{ $title->code }}" @endif
                    @if ($toggle && $title) wire:click="toggleFavourite(@js($title->code))" @endif>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="{{ $favourite ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" d="M12 20.5s-7.5-4.8-7.5-10.4A4.3 4.3 0 0 1 12 7.4a4.3 4.3 0 0 1 7.5 2.7c0 5.6-7.5 10.4-7.5 10.4z"/></svg>
            </button>
        @endif
    </div>
    <div class="nt-tile-meta"><b>{{ $name }}</b>@if ($sub)<span>{{ $sub }}</span>@endif</div>
</div>

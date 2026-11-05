{{--
    The landing for guests and players (plan §2): the hero carousel, whose first slide is the Kadi table with today's
    big match beside it, then the football list while sports is on (Phase 9), then the casino panel. The bet slip
    sits in the rail.
--}}
<div class="nt-lobby nt-view-in">
    <x-lobby.hero :slides="$heroSlides" :$seats :big-match="$sports ? $bigMatch : null" :$also :also-today="$alsoIsToday" />

    @if ($sports)
        <livewire:sports.fixture-list preview key="landing-football" />
    @endif

    @if ($casino)
        <section class="nt-panel nt-casino nt-reveal" aria-labelledby="casino-title"
                 x-data="{ theme: @js($casino['themes'][0]['key'] ?? null) }">
            <x-ui.section-head title="Casino" id="casino-title"
                               sub="{{ $casino['slotCount'] }} slots and {{ $casino['scratchCount'] }} scratch cards, played right here">
                <x-slot:actions>
                    <button type="button" class="nt-arrow" aria-label="Scroll slots left"
                            x-on:click="$root.querySelector('[data-shelf]:not([hidden]) .nt-tile-row')?.scrollBy({ left: -$root.clientWidth * .8, behavior: 'smooth' })">
                        <x-site.icon name="chev" class="rotate-90" />
                    </button>
                    <button type="button" class="nt-arrow" aria-label="Scroll slots right"
                            x-on:click="$root.querySelector('[data-shelf]:not([hidden]) .nt-tile-row')?.scrollBy({ left: $root.clientWidth * .8, behavior: 'smooth' })">
                        <x-site.icon name="chev" class="-rotate-90" />
                    </button>
                </x-slot:actions>
            </x-ui.section-head>

            <div class="nt-chips" role="group" aria-label="Slot themes">
                @foreach ($casino['themes'] as $shelf)
                    <button type="button" class="nt-chip" x-on:click="theme = @js($shelf['key'])"
                            x-bind:aria-pressed="theme === @js($shelf['key'])" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $shelf['label'] }}</button>
                @endforeach
            </div>

            @foreach ($casino['themes'] as $shelf)
                <div data-shelf="{{ $shelf['key'] }}" x-bind:hidden="theme !== @js($shelf['key'])" @unless ($loop->first) hidden @endunless>
                    <div class="nt-tile-row">
                        @foreach ($shelf['titles']->take(12) as $title)
                            <x-lobby.tile :title="$title" :favourites="$favourites" wire:key="{{ $shelf['key'] }}-{{ $title->code }}" />
                        @endforeach
                    </div>
                    @if ($shelf['key'] !== 'most-played' && $shelf['key'] !== 'featured')
                        <p class="mt-3 mb-0 text-right"><a class="nt-see-all" href="{{ route('casino', ['filter' => $shelf['key']]) }}" wire:navigate>All {{ $shelf['titles']->count() }} in {{ $shelf['label'] }}</a></p>
                    @endif
                </div>
            @endforeach

            @if ($casino['favouriteTitles']->isNotEmpty())
                <div class="nt-split">
                    <x-ui.section-head title="Your favourites" sub="The games you've hearted">
                        <x-slot:actions><a class="nt-see-all" href="{{ route('casino', ['filter' => 'favourites']) }}" wire:navigate>See all</a></x-slot:actions>
                    </x-ui.section-head>
                    <div class="nt-tile-row">
                        @foreach ($casino['favouriteTitles']->take(12) as $title)
                            <x-lobby.tile :title="$title" :favourites="$favourites" wire:key="fav-{{ $title->code }}" />
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($casino['scratch']->isNotEmpty())
                <div class="nt-split">
                    <x-ui.section-head title="Scratch cards" sub="Scratch the foil, see the prize">
                        <x-slot:actions><a class="nt-see-all" href="{{ route('casino', ['filter' => 'scratch-cards']) }}" wire:navigate>See all</a></x-slot:actions>
                    </x-ui.section-head>
                    <div class="nt-scratch">
                        @foreach ($casino['scratch'] as $title)
                            <x-lobby.tile :title="$title" :favourites="$favourites" wire:key="scratch-{{ $title->code }}" />
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($casino['collections'] !== [])
                <div class="nt-split">
                    <x-ui.section-head title="Collections" sub="Slots grouped by theme" />
                    <div class="nt-collections">
                        @foreach ($casino['collections'] as $collection)
                            <a class="nt-coll" href="{{ route('casino', ['filter' => $collection['key']]) }}" wire:navigate>
                                <span class="nt-stack" aria-hidden="true">
                                    @foreach ($collection['covers'] as $cover)
                                        <img src="{{ $cover }}" alt="" loading="lazy" width="320" height="480">
                                    @endforeach
                                </span>
                                <span class="nt-coll-label"><b>{{ $collection['label'] }}</b><span>{{ $collection['count'] }} {{ Str::plural('slot', $collection['count']) }}</span></span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    @endif
</div>

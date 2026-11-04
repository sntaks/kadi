<div class="nt-view-in">
    <article class="nt-game">
        <div class="nt-cover">
            @if ($title->thumbnail)
                <img src="{{ $title->thumbnail }}" alt="{{ $title->name }} cover art" width="320" height="480">
            @else
                <span class="nt-tile"><span class="nt-art"><span class="nt-art-fallback">{{ $title->name }}</span></span></span>
            @endif
        </div>
        <div class="min-w-0">
            <nav class="nt-crumb" aria-label="Breadcrumb">
                <a href="{{ $index[1] }}" wire:navigate>{{ $index[0] }}</a>
                @if ($title->collection)
                    <span aria-hidden="true"> › </span>{{ $title->kindLabel() }}
                @endif
            </nav>
            <h1>{{ $title->name }}</h1>
            <ul class="nt-facts">
                <li><x-ui.pill>{{ $title->typeLabel() }}</x-ui.pill></li>
                @if ($title->hasBadge('jackpot'))<li><x-ui.pill tone="gold">Jackpot</x-ui.pill></li>@endif
                <li><x-ui.pill>Plays in your browser</x-ui.pill></li>
            </ul>
            <p>{{ $description }}</p>
            <div class="nt-ctas">
                @if ($title->playUrl())
                    <x-ui.button size="lg" :href="$title->playUrl()" :data-needs-account="auth()->guest() ? '' : null">{{ auth()->check() ? 'Play '.$title->name : 'Log in to play' }}</x-ui.button>
                @else
                    <x-ui.pill tone="warn">Opens for play here soon</x-ui.pill>
                @endif
                @if ($favourites !== null)
                    @php($hearted = in_array($title->code, $favourites, true))
                    <x-ui.button variant="ghost" size="lg" wire:click="toggleFavourite(@js($title->code))" aria-pressed="{{ $hearted ? 'true' : 'false' }}">
                        {{ $hearted ? 'In your favourites' : 'Add to favourites' }}
                    </x-ui.button>
                @endif
            </div>
        </div>
    </article>

    @if ($more->isNotEmpty())
        <section class="mt-10" aria-labelledby="more-title">
            <x-ui.section-head title="More like this" id="more-title" />
            <div class="nt-tile-grid">
                @foreach ($more as $other)
                    <x-lobby.tile :title="$other" :favourites="$favourites" link="page" wire:key="more-{{ $other->code }}" />
                @endforeach
            </div>
        </section>
    @endif

    <script type="application/ld+json">
        {!! json_encode(array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'VideoGame',
            'name' => $title->name,
            'url' => $title->pageUrl(),
            'image' => $title->thumbnail ? url($title->thumbnail) : null,
            'description' => $description,
            'genre' => $title->typeLabel(),
            'gamePlatform' => 'Web browser',
            'applicationCategory' => 'Game',
            'publisher' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => url('/')],
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $index[0], 'item' => $index[1]],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $title->name, 'item' => $title->pageUrl()],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
</div>

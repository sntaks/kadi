<div class="nt-view-in">
    <x-ui.page-head crumb="Casino" :title="$page['title']" :description="$page['intro']" />

    @forelse ($groups as $group)
        <section class="mb-8" @if ($group['label']) aria-label="{{ $group['label'] }}" @endif>
            @if ($group['label'])
                <x-ui.section-head :title="$group['label']" :sub="$group['titles']->count().' '.Str::plural('slot', $group['titles']->count())" />
            @endif
            <div class="nt-tile-grid">
                @foreach ($group['titles'] as $title)
                    <x-lobby.tile :title="$title" :favourites="$favourites" link="page" wire:key="index-{{ $title->code }}" />
                @endforeach
            </div>
        </section>
    @empty
        <div class="nt-empty"><b>Nothing to play here right now</b>These games are taking a break. Check back soon.</div>
    @endforelse

    @if ($titles->isNotEmpty())
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $page['title'],
                'itemListElement' => $titles->filter(fn ($title) => $title->pageUrl())->values()->map(fn ($title, $i) => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $title->name,
                    'url' => $title->pageUrl(),
                ])->all(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
        </script>
    @endif
</div>

<div class="nt-predict nt-view-in" data-predict-live wire:poll.{{ (int) config('predict.feed.page_poll_seconds', 25) }}s.visible="sync">
    <header class="nt-pm-head">
        <h1>Predictions</h1>
        @if ($world['available'] && $world['total'] > 0)
            <span class="nt-pm-count nt-num">{{ number_format($world['total'] + $kadiTotal) }} {{ \Illuminate\Support\Str::plural('market', $world['total'] + $kadiTotal) }}</span>
        @endif
    </header>

    <div class="nt-pm-filters">
        <x-ui.chips label="Category">
            <x-ui.chip :pressed="$category === ''" wire:click="setCategory('')">All markets</x-ui.chip>
            @foreach ($categories as $key)
                <x-ui.chip :pressed="$category === $key" wire:click="setCategory('{{ $key }}')">{{ \App\Predict\Markets::categoryLabel($key) }}</x-ui.chip>
            @endforeach
        </x-ui.chips>
        <x-ui.tabs label="Sort markets">
            @foreach ($sorts as $key)
                <x-ui.tab :active="$sort === $key" wire:click="setSort('{{ $key }}')">{{ \App\Predict\Markets::sortLabel($key) }}</x-ui.tab>
            @endforeach
        </x-ui.tabs>
    </div>

    @if ($stale)
        <p class="nt-pm-stale" role="status">Prices may be out of date. Any bet uses the price shown when you place it.</p>
    @endif

    @unless ($canBet)
        <p class="nt-pm-note">Betting on predictions opens soon. You can follow the markets now.</p>
    @endunless

    <section aria-labelledby="pm-world">
        <x-ui.section-head id="pm-world" title="World markets" sub="Questions from Polymarket" />
        @if (! $world['available'])
            <div class="nt-empty nt-panel"><b>Markets can't be loaded right now</b>Please try again in a minute.</div>
        @elseif ($worldGroups === [])
            <div class="nt-empty nt-panel"><b>No markets here yet</b>Try another category.</div>
        @else
            <div class="nt-pm-grid">
                @foreach ($worldGroups as $group)
                    @if (count($group['markets']) > 1)
                        <x-predict.event-card :title="$group['title']" :markets="$group['markets']" :$now wire:key="pm-event-{{ md5($group['title']) }}" />
                    @else
                        <x-predict.market-card :market="$group['markets'][0]" :$now wire:key="pm-card-{{ $group['markets'][0]->id }}" />
                    @endif
                @endforeach
            </div>

            @php($shown = collect($worldGroups)->sum(fn ($g) => count($g['markets'])))
            <div class="nt-pm-more-bar">
                <span class="nt-num" aria-live="polite">Showing {{ number_format($shown) }} of {{ number_format($world['total']) }}</span>
                @if ($world['more'])
                    <x-ui.button variant="ghost" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore" data-predict-load-more>
                        <span wire:loading.remove wire:target="loadMore">Load more markets</span>
                        <span wire:loading wire:target="loadMore">Loading…</span>
                    </x-ui.button>
                @elseif ($shown < $world['total'])
                    <span class="nt-pm-note">Pick a category to see the rest.</span>
                @endif
            </div>
        @endif
    </section>

    @if ($kadiGroups !== [])
        <section aria-labelledby="pm-kadi">
            <x-ui.section-head id="pm-kadi" title="Kadi markets" sub="Our own questions" />
            <div class="nt-pm-grid">
                @foreach ($kadiGroups as $group)
                    @if (count($group['markets']) > 1)
                        <x-predict.event-card :title="$group['title']" :markets="$group['markets']" :$now wire:key="pm-kevent-{{ md5($group['title']) }}" />
                    @else
                        <x-predict.market-card :market="$group['markets'][0]" :$now wire:key="pm-kcard-{{ $group['markets'][0]->id }}" />
                    @endif
                @endforeach
            </div>
        </section>
    @endif
</div>

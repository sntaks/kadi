<div class="nt-predict nt-view-in" data-predict-live wire:poll.{{ (int) config('predict.feed.page_poll_seconds', 25) }}s.visible="sync">
    <header class="nt-pm-head">
        <h1>Predictions</h1>
        @php($count = $kadi['total'] + $world['total'])
        @if ($count > 0)
            <span class="nt-pm-count nt-num">{{ number_format($count) }} {{ \Illuminate\Support\Str::plural('market', $count) }}</span>
        @endif
        @auth
            <x-ui.button variant="ghost" size="sm" class="nt-pm-head-action" :href="route('predictions.bets')" wire:navigate>My bets</x-ui.button>
        @endauth
    </header>

    <div class="nt-pm-filters">
        <x-ui.chips label="Markets">
            <x-ui.chip :pressed="$shown === ''" wire:click="setSource('')">All markets</x-ui.chip>
            @foreach ($sections as $key)
                <x-ui.chip :pressed="$shown === $key" wire:click="setSource('{{ $key }}')">{{ \App\Predict\Markets::sectionLabel($key) }}</x-ui.chip>
            @endforeach
        </x-ui.chips>
        <x-ui.chips label="Category">
            <x-ui.chip :pressed="$category === ''" wire:click="setCategory('')">All categories</x-ui.chip>
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

    {{-- With both sections, Local markets shows only when there are some (World says when nothing can be loaded). --}}
    @if ($shown === 'local' || ($shown === '' && $kadiGroups !== []))
        <section aria-labelledby="pm-kadi">
            <x-ui.section-head id="pm-kadi" :title="\App\Predict\Markets::sectionLabel('local')" sub="Our own questions" />
            @if (! $kadi['available'])
                <div class="nt-empty nt-panel"><b>Markets can't be loaded right now</b>Please try again in a minute.</div>
            @elseif ($kadiGroups === [])
                <div class="nt-empty nt-panel"><b>No markets here yet</b>Try another category.</div>
            @else
                <div class="nt-pm-grid">
                    @foreach ($kadiGroups as $group)
                        @if (count($group['markets']) > 1)
                            <x-predict.event-card :title="$group['title']" :markets="$group['markets']" :$now wire:key="pm-kevent-{{ md5($group['title']) }}" />
                        @else
                            <x-predict.market-card :market="$group['markets'][0]" :$now wire:key="pm-kcard-{{ $group['markets'][0]->id }}" />
                        @endif
                    @endforeach
                </div>

                <x-predict.more-bar :list="$kadi" :groups="$kadiGroups" action="loadMoreKadi" />
            @endif
        </section>
    @endif

    @if ($shown !== 'local')
        <section aria-labelledby="pm-world">
            <x-ui.section-head id="pm-world" :title="\App\Predict\Markets::sectionLabel('world')" sub="Questions from Polymarket" />
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

                <x-predict.more-bar :list="$world" :groups="$worldGroups" action="loadMoreWorld" />
            @endif
        </section>
    @endif
</div>

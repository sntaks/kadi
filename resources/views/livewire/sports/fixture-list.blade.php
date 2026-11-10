<section class="nt-panel nt-football" aria-labelledby="football-{{ $this->getId() }}" wire:poll.300s.visible>
    <div class="nt-sb-head">
        <h2 id="football-{{ $this->getId() }}"><x-site.icon :name="$icon" />{{ $heading }}</h2>
        <label class="nt-market">
            <span class="sr-only">Market</span>
            <select wire:model.live="marketKey">
                @foreach ($markets as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <div class="nt-tabs nt-sb-tabs" role="tablist" aria-label="When">
        @foreach (\App\Sportsbook\Catalogue\FixtureListing::TABS as $key => $label)
            <button type="button" role="tab" class="nt-tab" wire:click="$set('tab', '{{ $key }}')"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($this->fixtures->isEmpty())
        <div class="nt-empty nt-sb-pad"><b>No matches priced for this day yet</b>Prices open about a week before kick-off. Try another tab.</div>
    @else
        <div class="nt-fx-cols" @if ($columns === 2) data-two @endif aria-hidden="true">
            <span>Time</span><span>Match</span>
            @foreach ($labels as $label)<span>{{ $label }}</span>@endforeach
            @if ($columns === 2)<span>{{ $marketKey === 'totals' ? 'Line' : '' }}</span>@endif
            <span></span>
        </div>

        <div wire:loading.class="opacity-60" wire:target="tab, marketKey" class="transition-opacity">
            @foreach ($groups as $key => $fixtures)
                @php [$date, $competition] = explode('|', $key, 2); @endphp
                <h3 class="nt-fx-group">{{ \App\Livewire\Sports\FixtureList::dayName($date) }} · {{ $competition }}</h3>
                @foreach ($fixtures as $fixture)
                    <x-sports.fixture-row :$fixture :market-key="$marketKey" :$columns />
                @endforeach
            @endforeach
        </div>

        @if (! $preview && $this->total > $this->fixtures->count())
            <div class="nt-sb-more" x-intersect.margin.600px="$wire.showMore()">
                <button type="button" class="nt-btn nt-btn-ghost nt-btn-sm" wire:click="showMore">
                    <span wire:loading.remove wire:target="showMore">Show more matches</span>
                    <span wire:loading wire:target="showMore">Loading…</span>
                </button>
            </div>
        @endif
    @endif

    <div class="nt-sb-foot">
        <span class="nt-num">{{ $this->fixtures->count() }} of {{ $this->total }} {{ \Illuminate\Support\Str::plural('match', $this->total) }} shown</span>
        @if ($preview && $this->total > 0)
            <a href="{{ route('sports') }}" wire:navigate>All {{ $this->total }} {{ \Illuminate\Support\Str::plural('match', $this->total) }}</a>
        @endif
    </div>
</section>

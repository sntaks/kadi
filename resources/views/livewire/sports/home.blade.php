<div class="nt-sports nt-view-in">
    <x-ui.page-head :title="$name" description="Prices on today's matches and the week ahead. Tap a price to add it to your bet slip." />

    @if ($jackpot)
        <x-sports.jackpot-banner :$jackpot />
    @endif

    <div class="nt-sport-wrap"><div class="nt-sport-layout">
        <x-sports.sport-nav :$sport :$sports :$competitions :$tab />
        <div class="nt-sport-main">
            @if ($competitions === [] && $sport !== 'football')
                {{-- A sport out of season (tennis in December, rugby before February): nothing is on offer at all. --}}
                <div class="nt-panel nt-empty nt-sb-pad"><b>No {{ $name }} on offer right now</b>Events show here about a week before they start.</div>
            @else
                <livewire:sports.fixture-list :tab="$tab" :sport="$sport" :key="'sports-list-'.$sport.'-'.$tab" heading="Matches" :icon="$icon" />
            @endif
        </div>
    </div></div>
</div>

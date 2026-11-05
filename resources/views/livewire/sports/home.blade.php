<div class="nt-sports nt-view-in">
    <x-ui.page-head title="Football" description="Prices on today's matches and the week ahead. Tap a price to add it to your bet slip." />

    @if ($jackpot)
        <x-sports.jackpot-banner :$jackpot />
    @endif

    @if ($competitions !== [])
        <nav class="nt-chips nt-comp-chips" aria-label="Competitions">
            @foreach ($competitions as $competition)
                <a class="nt-chip" href="{{ route('sports.competition', $competition['slug']) }}" wire:navigate>{{ $competition['name'] }} <span class="nt-num">{{ $competition['count'] }}</span></a>
            @endforeach
        </nav>
    @endif

    <livewire:sports.fixture-list :tab="$tab" :key="'sports-list-'.$tab" heading="Matches" />
</div>

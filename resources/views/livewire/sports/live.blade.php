<div class="nt-sports nt-view-in" wire:poll.15s.visible>
    @if ($inPlayOn)
        <x-ui.page-head title="In play" crumb="Sports" description="Matches being played now, with live odds where we offer them. Live bets are checked for a few seconds before they're placed." />
    @else
        <x-ui.page-head title="In play" crumb="Sports" description="Matches being played now. In-play betting is coming soon: betting on a match closes at kick-off." />
    @endif

    <section class="nt-panel">
        @forelse ($fixtures->groupBy(fn ($f) => $f->competition->name) as $competition => $group)
            <h3 class="nt-fx-group">{{ $competition }}</h3>
            @foreach ($group as $fixture)
                @php
                    $market = $fixture->markets->first();
                    $live = $inPlayOn && $market !== null && $market->isOpen() && \App\Sportsbook\Support\InPlay::coversMarket($market, $fixture);
                    $hasScore = $fixture->home_score !== null && $fixture->away_score !== null;
                @endphp
                <div class="nt-row nt-live-row" @if ($live) data-live @endif data-match="{{ $fixture->name() }}" data-market="{{ $market?->name() }}" wire:key="live-{{ $fixture->id }}">
                    <span class="nt-row-icon" aria-hidden="true"><x-site.icon :name="\App\Sportsbook\Support\SportProfile::forSport($fixture->competition->sport)->icon" /></span>
                    <a class="nt-live-teams" href="{{ route('sports.match', $fixture) }}" x-on:click="$openMatch($event, {{ $fixture->id }}, @js($fixture->name()))">
                        <span class="nt-live-team"><b>{{ $fixture->home_team }}</b>@if ($hasScore)<span class="nt-score nt-num">{{ $fixture->home_score }}</span>@endif</span>
                        <span class="nt-live-team"><b>{{ $fixture->away_team }}</b>@if ($hasScore)<span class="nt-score nt-num">{{ $fixture->away_score }}</span>@endif</span>
                        <span class="nt-meta nt-num"><span class="nt-live-dot" aria-hidden="true"></span>Live · kicked off {{ $fixture->kicks_off_at->timezone(config('sportsbook.timezone'))->format('H:i') }}</span>
                    </a>
                    @if ($live)
                        <span class="nt-live-odds">
                            @foreach ($market->selections as $selection)
                                <x-sports.odds :$selection :$fixture :open="true" wire:key="live-selection-{{ $selection->id }}" />
                            @endforeach
                        </span>
                    @else
                        <span class="nt-pill">Betting closed</span>
                    @endif
                </div>
            @endforeach
        @empty
            <div class="nt-empty nt-sb-pad"><b>No matches in play right now</b>Matches show here from kick-off until full time.</div>
        @endforelse
    </section>
</div>

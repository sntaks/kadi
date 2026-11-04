<div class="nt-sports nt-view-in" wire:poll.60s.visible>
    <x-ui.page-head title="In play" crumb="Football" description="Matches being played now. In-play betting is coming soon: betting on a match closes at kick-off." />

    <section class="nt-panel">
        @forelse ($fixtures->groupBy(fn ($f) => $f->competition->name) as $competition => $group)
            <h3 class="nt-fx-group">{{ $competition }}</h3>
            @foreach ($group as $fixture)
                <div class="nt-row nt-live-row">
                    <span class="nt-row-icon" aria-hidden="true"><x-site.icon name="ball" /></span>
                    <span><b>{{ $fixture->name() }}</b><span class="nt-meta nt-num">Kicked off {{ $fixture->kicks_off_at->timezone(config('sportsbook.timezone'))->format('H:i') }}</span></span>
                    <span class="nt-pill">Betting closed</span>
                </div>
            @endforeach
        @empty
            <div class="nt-empty nt-sb-pad"><b>No matches in play right now</b>Matches show here from kick-off until full time.</div>
        @endforelse
    </section>
</div>

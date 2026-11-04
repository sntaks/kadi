<div class="nt-sports nt-view-in">
    <x-ui.page-head title="Search matches" crumb="Football" />

    <form class="nt-sports-search" wire:submit.prevent role="search">
        <label class="nt-stake"><x-site.icon name="search" /><span class="sr-only">Team or competition</span>
            <input type="search" wire:model.live.debounce.400ms="q" placeholder="Team or competition" autocomplete="off" autofocus>
        </label>
    </form>

    <section class="nt-panel nt-football">
        @if (mb_strlen(trim($q)) < 2)
            <div class="nt-empty nt-sb-pad"><b>Type a team or a competition</b>At least two letters.</div>
        @elseif ($fixtures->isEmpty())
            <div class="nt-empty nt-sb-pad"><b>No matches for "{{ trim($q) }}"</b>Only matches open for betting are listed.</div>
        @else
            @foreach ($fixtures as $fixture)
                <x-sports.fixture-row :$fixture />
            @endforeach
        @endif
    </section>
</div>

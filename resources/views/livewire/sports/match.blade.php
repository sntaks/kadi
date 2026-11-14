<div class="nt-sports nt-view-in" @if (\App\Sportsbook\Support\InPlay::isTrading($this->fixture)) wire:poll.15s.visible @else wire:poll.120s.visible @endif data-match-page>
    <x-sports.match-details :fixture="$this->fixture" :markets-by-tab="$this->marketsByTab" :$tab />
</div>

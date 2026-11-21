@php
    use App\Sportsbook\Enums\JackpotEntryStatus;
    use App\Sportsbook\Support\Money;
    $tz = config('sportsbook.timezone');
@endphp
{{-- Activity › Sports: the player's bets (open, settled) as slips, and jackpot entries, newest first. --}}
<div class="px-[22px] pb-2 max-[640px]:px-4">
    <div class="nt-chips" role="group" aria-label="Which bets">
        @foreach (\App\Livewire\Account\Activity::SPORTS_VIEWS as $key => $label)
            <button type="button" class="nt-chip" wire:click="showSports('{{ $key }}')" aria-pressed="{{ $sportsView === $key ? 'true' : 'false' }}" data-test="sports-view-{{ $key }}">{{ $label }}</button>
        @endforeach
    </div>
</div>

@if ($page === null || $page->isEmpty())
    <div class="nt-empty px-[22px]">
        @if ($sportsView === 'jackpot')
            <b>No jackpot entries yet</b>Your sports jackpot entries show here.
        @elseif ($sportsView === 'open')
            <b>No open bets</b>Bets waiting on a result show here.
        @else
            <b>No settled bets yet</b>Your bets show here once their matches are played.
        @endif
        @if (Route::has('sports'))
            <a class="nt-btn nt-btn-ghost nt-btn-sm mt-3" href="{{ route('sports') }}" wire:navigate>See today's football</a>
        @endif
    </div>
@else
    @if ($sportsView === 'jackpot')
        <ul class="nt-list">
            @foreach ($page as $item)
                @php
                    $status = match ($item->status) {
                        JackpotEntryStatus::Pending => ['Checking…', null],
                        JackpotEntryStatus::Open => ['Open', null],
                        JackpotEntryStatus::Won => ['Won '.$item->correct_count.' right', 'in'],
                        JackpotEntryStatus::Lost => ['Lost · '.$item->correct_count.' right', null],
                        JackpotEntryStatus::Refunded => ['Refunded', null],
                        JackpotEntryStatus::Rejected => ['Not placed', null],
                    };
                @endphp
                <li class="nt-row" wire:key="entry-{{ $item->id }}">
                    <span class="nt-row-icon" aria-hidden="true"><x-site.icon name="gift" /></span>
                    <span class="min-w-0">
                        <b>{{ $item->jackpot?->name ?? 'Jackpot' }}</b>
                        <span class="nt-meta nt-num">{{ $item->placed_at->timezone($tz)->format('j M, H:i') }} · {{ $item->reference }} · {{ Money::format($item->stake) }}</span>
                    </span>
                    <span class="nt-trail nt-num">
                        @if ($item->status === JackpotEntryStatus::Won)
                            <span class="nt-in">+{{ Money::format($item->prize - ($item->tax ?? 0)) }}</span>
                            @if ($item->tax)<span class="nt-meta">after {{ Money::format($item->tax) }} tax</span>@endif
                        @else
                            <span class="nt-meta">{{ $status[0] }}</span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @else
        <ol class="nt-bets" aria-label="Your bets">
            @foreach ($page as $bet)
                @include('livewire.account.partials.sports-bet-card', ['bet' => $bet])
            @endforeach
        </ol>
    @endif
    @if ($page->lastPage() > 1)
        <div class="nt-list-foot">
            <span>Page {{ $page->currentPage() }} of {{ $page->lastPage() }}</span>
            <span class="flex gap-2">
                <x-ui.button variant="ghost" size="sm" wire:click="goTo({{ $page->currentPage() - 1 }})" :disabled="$page->onFirstPage()">Newer</x-ui.button>
                <x-ui.button variant="ghost" size="sm" wire:click="goTo({{ $page->currentPage() + 1 }})" :disabled="! $page->hasMorePages()">Older</x-ui.button>
            </span>
        </div>
    @endif
@endif

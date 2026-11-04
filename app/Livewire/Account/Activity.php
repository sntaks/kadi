<?php

namespace App\Livewire\Account;

use App\Account\Wagers;
use App\Models\User;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Support\SportsPages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /activity (plan Phase 6): everything the player played. Kadi keeps its game history and dispute tools
 * (App\Livewire\Games\History, unchanged); slots and scratch cards list the player's wagers from KadiApi.
 * Sports lists the player's own bets and jackpot entries from sb_bets / sb_jackpot_entries (Phase 9).
 */
class Activity extends Component
{
    /** @var array<string, string> */
    public const SPORTS_VIEWS = ['open' => 'Open', 'settled' => 'Settled', 'jackpot' => 'Jackpot'];

    private const SPORTS_PER_PAGE = 15;

    #[Url(as: 'game', except: 'kadi')]
    public string $tab = 'kadi';

    public int $page = 1;

    /** On the Sports tab: open (and being checked), settled, or jackpot entries. */
    #[Url(as: 'show', except: 'open')]
    public string $sportsView = 'open';

    public bool $loaded = false;

    public function mount(): void
    {
        if (! array_key_exists($this->tab, $this->tabs())) {
            $this->tab = 'kadi';
        }
    }

    public function show(string $tab): void
    {
        if (array_key_exists($tab, $this->tabs())) {
            $this->tab = $tab;
            $this->page = 1;
            $this->loaded = false;
        }
    }

    /** wire:init: the wagers list loads after the page shows, never holding it up. */
    public function load(): void
    {
        $this->loaded = true;
    }

    public function showSports(string $view): void
    {
        if (array_key_exists($view, self::SPORTS_VIEWS)) {
            $this->sportsView = $view;
            $this->page = 1;
        }
    }

    public function goTo(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * The player's own bets or jackpot entries, newest first. Local records: the Kadi API holds the money.
     *
     * @return LengthAwarePaginator<int, Bet>|LengthAwarePaginator<int, JackpotEntry>
     */
    private function sportsPage(User $user): LengthAwarePaginator
    {
        if ($this->sportsView === 'jackpot') {
            return JackpotEntry::query()->where('user_id', $user->id)->with('jackpot')
                ->latest('placed_at')->latest('id')
                ->paginate(self::SPORTS_PER_PAGE, page: $this->page);
        }

        return Bet::query()->where('user_id', $user->id)
            ->whereIn('status', $this->sportsView === 'open'
                ? [BetStatus::Open, BetStatus::Pending]
                : [BetStatus::Won, BetStatus::Lost, BetStatus::Void, BetStatus::Cancelled, BetStatus::Rejected])
            ->with('legs')
            ->latest($this->sportsView === 'open' ? 'placed_at' : 'settled_at')->latest('id')
            ->paginate(self::SPORTS_PER_PAGE, page: $this->page);
    }

    /** @return array<string, string> */
    public function tabs(): array
    {
        return array_filter([
            'kadi' => 'Kadi',
            'slots' => 'Slots',
            'scratch' => 'Scratch cards',
            'sports' => SportsPages::on() ? 'Sports' : null,
        ]);
    }

    public function render(Wagers $wagers): View
    {
        $user = auth()->user();
        $list = null;

        if ($this->loaded && in_array($this->tab, ['slots', 'scratch'], true) && $user instanceof User) {
            $list = $wagers->page($user, $this->tab, $this->page);
        }

        if (! array_key_exists($this->sportsView, self::SPORTS_VIEWS)) {
            $this->sportsView = 'open';
        }

        return view('livewire.account.activity', [
            'tabs' => $this->tabs(),
            'list' => $list,
            'sports' => $this->tab === 'sports' && $user instanceof User ? $this->sportsPage($user) : null,
        ])
            ->layout('layouts.app')
            ->title('Activity · Kadi');
    }
}

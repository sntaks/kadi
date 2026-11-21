<?php

namespace App\Livewire\Account;

use App\Account\Wagers;
use App\Games\Game;
use App\Games\GameCatalogue;
use App\Models\User;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Support\PredictPages;
use App\Support\SportsPages;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /activity (plan Phase 6): everything the player played. Kadi keeps its game history and dispute tools
 * (App\Livewire\Games\History, unchanged); slots and scratch cards list the player's wagers from KadiApi.
 * Sports lists the player's own bets and jackpot entries from sb_bets / sb_jackpot_entries (Phase 9), each bet as a
 * slip with its picks. Every other casino game KadiApi offers (Coin Flip, Roulette…) lists its wagers like slots,
 * and Predictions shows the prediction bets (App\Livewire\Predict\MyBets, embedded). The games are picked from one
 * dropdown (<x-account.game-select>).
 */
class Activity extends Component
{
    /** @var array<string, string> */
    public const SPORTS_VIEWS = ['open' => 'Open', 'settled' => 'Settled', 'jackpot' => 'Jackpot'];

    private const SPORTS_PER_PAGE = 15;

    /** Always offered, so a catalogue outage never hides a player's slot or scratch card history. */
    private const CASINO = ['slots' => 'Slots', 'scratch' => 'Scratch cards'];

    /** Games with a page of their own here, never listed again from the catalogue. */
    private const NOT_CASINO = ['kadi', 'sports', 'sportsbook', 'predictions', 'predict'];

    /** @var array<string, string> */
    public const ICONS = ['kadi' => 'kadi', 'sports' => 'football', 'slots' => 'slot', 'scratch' => 'gift', 'predictions' => 'predict'];

    /** @var array<string, string>|null */
    private ?array $tabs = null;

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
            ->with(['legs.fixture.result'])
            ->latest($this->sportsView === 'open' ? 'placed_at' : 'settled_at')->latest('id')
            ->paginate(self::SPORTS_PER_PAGE, page: $this->page);
    }

    /**
     * Kadi, Sports, every casino game with a wagers history (slots and scratch cards always, the rest only while
     * the catalogue offers them, never from config alone), then Predictions.
     *
     * @return array<string, string>
     */
    public function tabs(): array
    {
        if ($this->tabs !== null) {
            return $this->tabs;
        }

        $casino = self::CASINO;

        foreach (app(GameCatalogue::class)->games() as $code => $game) {
            /** @var Game $game */
            if (! in_array($code, self::NOT_CASINO, true) && preg_match('/^[a-z0-9_]{1,40}$/', (string) $code)) {
                $casino[$code] ??= $game->name;
            }
        }

        return $this->tabs = array_filter([
            'kadi' => 'Kadi',
            'sports' => SportsPages::on() ? 'Sports' : null,
            ...$casino,
            'predictions' => PredictPages::on() ? 'Predictions' : null,
        ]);
    }

    /** Whether a tab lists casino wagers from KadiApi (`GET customers/{id}/wagers?game=`). */
    public function isCasino(string $tab): bool
    {
        return ! in_array($tab, ['kadi', 'sports', 'predictions'], true) && array_key_exists($tab, $this->tabs());
    }

    public function render(Wagers $wagers): View
    {
        $user = auth()->user();
        $list = null;

        if ($this->loaded && $this->isCasino($this->tab) && $user instanceof User) {
            $list = $wagers->page($user, $this->tab, $this->page);
        }

        if (! array_key_exists($this->sportsView, self::SPORTS_VIEWS)) {
            $this->sportsView = 'open';
        }

        return view('livewire.account.activity', [
            'tabs' => $this->tabs(),
            'casino' => $this->isCasino($this->tab),
            'list' => $list,
            'sports' => $this->tab === 'sports' && $user instanceof User ? $this->sportsPage($user) : null,
        ])
            ->layout('layouts.site', ['shell' => 'app'])
            ->title('Activity · Kadi');
    }
}

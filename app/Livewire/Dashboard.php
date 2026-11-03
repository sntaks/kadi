<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\WalletBalanceFetcher;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard | Kadi')]
class Dashboard extends Component
{
    public bool $showComingSoonModal = false;

    public string $selectedGame = '';

    /** Both balance caches are cold: loadBalance() fetches after first paint, the card shows a skeleton. */
    public bool $needsLoad = false;

    /** Drives the echo-private:user.{userId} listener on syncBalance() below. */
    public int $userId = 0;

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        $this->userId = $user->id;

        $this->needsLoad = $user->linked_id && app(WalletBalanceFetcher::class)->cached($user) === null;
    }

    /**
     * wire:init: fetch the balance a cold cache could not show. Shares the header widget's lock, so
     * both loading at once cost one KadiApi call; a fresh fetch tells the header to resync.
     */
    public function loadBalance(): void
    {
        $this->needsLoad = false;

        /** @var User $user */
        $user = auth()->user();

        if (app(WalletBalanceFetcher::class)->fetch($user)['fresh']) {
            $this->dispatch('wallet-refreshed');
        }
    }

    /**
     * Re-render from the shared cache after any balance refresh (header, wallet page) or wallet
     * webhook broadcast. A webhook that lands before loadBalance() has run fills the cache, so the
     * skeleton gives way to the balance.
     */
    #[On('wallet-refreshed')]
    #[On('echo-private:user.{userId},.wallet.updated')]
    public function syncBalance(): void
    {
        /** @var User $user */
        $user = auth()->user();

        if ($this->needsLoad && app(WalletBalanceFetcher::class)->cached($user) !== null) {
            $this->needsLoad = false;
        }
    }

    /**
     * The external play site link, carrying this user's Kadi game
     * identity when one is known. The app's own linked google_id is
     * authoritative; the kadi-accounts mirror is only a fallback.
     */
    public function buildPlayKadiUrl(): void
    {
        //
    }

    /**
     * Deterministic "live" lobby numbers derived from the time of day:
     * quiet mornings, busy evenings — identical for every visitor and
     * every render within the same minute (no per-render random jumps).
     *
     * @return array{liveTables: int, activeGames: int, onlineUsers: int}
     */
    public function liveStats(CarbonImmutable $now): array
    {
        $hour = (float) $now->format('G') + ((int) $now->format('i')) / 60;
        $ramp = min(max(($hour - 9) / 12, 0), 1);
        $wave = sin($hour / 24 * 2 * pi());

        return [
            'liveTables' => 18 + (int) round(10 * $ramp),
            'activeGames' => 120 + (int) round(30 * $ramp),
            'onlineUsers' => (int) round(850 + 2400 * $ramp + 180 * $wave),
        ];
    }

    /**
     * Progressive pool seeded at midnight and growing steadily through
     * the day until the nightly draw — deterministic across renders.
     */
    public function progressiveJackpot(CarbonImmutable $now): int
    {
        return 110_452_969 + $now->secondsSinceMidnight() * 11;
    }

    /**
     * Seconds until the next draw (21:00 app time, rolling to tomorrow).
     */
    public function secondsUntilNextDraw(CarbonImmutable $now): int
    {
        $draw = $now->setTime(21, 0);

        if ($now->greaterThanOrEqualTo($draw)) {
            $draw = $draw->addDay();
        }

        return max(0, (int) $now->diffInSeconds($draw));
    }

    public function render(): Factory|View
    {
        /** @var User $user */
        $user = auth()->user();

        $recentTransactions = $user
            ->transactions()
            ->latest()
            ->take(5)
            ->get();

        // Same caches as the header widget, so this card can't contradict it. Null means unknown
        // (still loading, or the fetch failed), never 0; an unlinked player has no wallet yet: 0.
        $kadiBalance = app(WalletBalanceFetcher::class)->cached($user) ?? ($user->linked_id ? null : 0.0);

        $now = now();
        $stats = $this->liveStats($now);

        return view('livewire.dashboard', [
            'recentTransactions' => $recentTransactions,
            'kadiBalance' => $kadiBalance,
            'jackpotAmount' => $this->progressiveJackpot($now),
            'drawInSeconds' => $this->secondsUntilNextDraw($now),
            'kadiPlaying' => (int) round(280 + 520 * min(max((($now->hour + $now->minute / 60) - 9) / 12, 0), 1)),
        ] + $stats)
            ->layout('layouts.app')
            ->layoutData([
                'noindex' => true,
                'page' => 'dashboard',
            ]);
    }
}

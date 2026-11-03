<?php

namespace App\Livewire;

use App\Games\Favourites;
use App\Games\KadiTables;
use App\Games\Lobby;
use App\Livewire\Concerns\TogglesFavourites;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Livewire;

#[Title('Kadi Online — Play the Kenyan Kadi Card Game')]
class Welcome extends Component
{
    use TogglesFavourites;

    /** Players at the Kadi tables now (App\Games\KadiTables); 0 when the counts are unknown. */
    public function livePlayers(): int
    {
        return app(KadiTables::class)->total() ?? 0;
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $user = auth()->user();

        return $user && $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }

    public function resendVerificationNotification(): void
    {
        $user = auth()->user();

        if (! $user || $user->hasVerifiedEmail()) {
            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        if (config('platform.ui_v2')) {
            return $this->landing();
        }

        return view('livewire.welcome', [
            'livePlayers' => $this->livePlayers(),
            'users' => User::count(),
        ])
            ->layout('layouts.guest')
            ->layoutData([
                'description' => 'Play Kadi online, the Kenyan Kadi card game. Free to join, real opponents, tournaments with prize pools and M-Pesa deposits. Sign up and play Kadi today.',
                'page' => 'home',
            ]);
    }

    /**
     * The new landing (plan §2, Phase 5): the Kadi table with real seat counts, then the casino panel
     * once games open inside the site. One page for guests and players; the dashboard redirects here.
     */
    private function landing(): \Illuminate\Contracts\View\View
    {
        $user = auth()->user();
        $user = $user instanceof User ? $user : null;
        $casino = null;
        $favourites = null;

        if (config('platform.token_launch')) {
            $lobby = app(Lobby::class);
            $favourites = $user ? $this->favouriteCodes() : null;
            $slots = $lobby->slots();
            $scratch = $lobby->scratchCards();

            $casino = [
                'themes' => $lobby->themes(),
                'scratch' => $scratch,
                'collections' => $lobby->collections(),
                'slotCount' => $slots->count(),
                'scratchCount' => $scratch->count(),
                'favouriteTitles' => $user ? app(Favourites::class)->titles($user, $favourites) : collect(),
            ];
        }

        // A heart tap re-renders this page: it must never wait on the game server for the seat counts.
        $tables = app(KadiTables::class);
        $seats = Livewire::isLivewireRequest() ? $tables->lastGood() : $tables->seats();

        return view('livewire.lobby.home', [
            'seats' => $seats,
            'casino' => $casino,
            'favourites' => $favourites,
        ])
            ->layout('layouts.site', [
                'shell' => 'app',
                'noindex' => false,
                'page' => 'home',
                'description' => $casino
                    ? 'Play Kadi online, the Kenyan card game, head to head for real money, plus slots and scratch cards. One wallet, M-Pesa deposits.'
                    : 'Play Kadi online, the Kenyan card game, head to head for real money. Singles, tournaments and jackpots, with M-Pesa deposits.',
            ])
            ->title($casino ? 'Kadi Online — Play Kadi, slots and scratch cards' : 'Kadi Online — Play the Kenyan Kadi Card Game');
    }
}

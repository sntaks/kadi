<?php

namespace App\Livewire;

use App\Games\Favourites;
use App\Games\KadiTables;
use App\Games\Lobby;
use App\Livewire\Concerns\TogglesFavourites;
use App\Models\User;
use App\Sportsbook\Catalogue\SportsLobby;
use App\Support\SportsPages;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Livewire;

#[Title('Kadi Online — Play the Kenyan Kadi Card Game')]
class Welcome extends Component
{
    use TogglesFavourites;

    /**
     * The landing (plan §2, Phase 5): the Kadi table with real seat counts, then the casino panel
     * once games open inside the site. One page for guests and players; /dashboard redirects here.
     */
    public function render(): View
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

        // Football beside and under the Kadi table once the sports pages are on (plan §2, Phase 9).
        $sports = SportsPages::on();
        $bigMatch = $sports ? app(SportsLobby::class)->bigMatch() : null;

        return view('livewire.lobby.home', [
            'seats' => $seats,
            'casino' => $casino,
            'favourites' => $favourites,
            'sports' => $sports,
            'bigMatch' => $bigMatch,
            'alsoToday' => $bigMatch ? app(SportsLobby::class)->alsoToday($bigMatch) : collect(),
        ])
            ->layout('layouts.site', [
                'shell' => 'app',
                'rail' => $sports,
                'noindex' => false,
                'page' => 'home',
                'og' => 'og-lobby.jpg',
                'description' => $casino
                    ? 'Play Kadi online, the Kenyan card game, head to head for real money, plus slots and scratch cards. One wallet, M-Pesa deposits.'
                    : 'Play Kadi online, the Kenyan card game, head to head for real money. Singles, tournaments and jackpots, with M-Pesa deposits.',
            ])
            ->title($casino ? 'Kadi Online — Play Kadi, slots and scratch cards' : 'Kadi Online — Play the Kenyan Kadi Card Game');
    }
}

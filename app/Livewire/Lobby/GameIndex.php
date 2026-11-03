<?php

namespace App\Livewire\Lobby;

use App\Games\GameTitle;
use App\Games\Lobby;
use App\Livewire\Concerns\TogglesFavourites;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /slots and /scratch-cards: crawlable lists of every title of one game, each linking to its own
 * game page (/games/{slug}). Slots are grouped by collection.
 */
class GameIndex extends Component
{
    use TogglesFavourites;

    public const PAGES = [
        'slots' => [
            'title' => 'Online slots',
            'meta' => 'Kadi slots',
            'intro' => 'Every slot plays right here in the site with your Kadi wallet: spin, win and cash out to M-Pesa.',
            'description' => 'Play online slots on Kadi: themed slots with jackpots, played in your browser with one wallet and M-Pesa deposits and withdrawals.',
        ],
        'scratch' => [
            'title' => 'Scratch cards',
            'meta' => 'Kadi scratch cards',
            'intro' => 'Scratch the foil and see the prize straight away. Every card plays right here with your Kadi wallet.',
            'description' => 'Play online scratch cards on Kadi: instant prizes, played in your browser with one wallet and M-Pesa deposits and withdrawals.',
        ],
    ];

    #[Locked]
    public string $game = 'slots';

    public function mount(string $game): void
    {
        abort_unless(array_key_exists($game, self::PAGES), 404);

        $this->game = $game;
    }

    public function render(Lobby $lobby): View
    {
        $user = auth()->user();
        $titles = $lobby->titlesOf($this->game);
        $page = self::PAGES[$this->game];

        // Slots by collection, in config order; anything without one at the end.
        $groups = $this->game === 'slots'
            ? $titles->groupBy(fn (GameTitle $title) => $title->collection ?? '')
                ->sortBy(fn ($group, $key) => $key === '' ? PHP_INT_MAX : (int) array_search($key, array_keys(config('games.collections', [])), true))
                ->map(fn ($group, $key) => ['label' => $key === '' ? 'More slots' : config("games.collections.{$key}.label"), 'titles' => $group->values()])
                ->values()
            : collect([['label' => null, 'titles' => $titles]]);

        return view('livewire.lobby.game-index', [
            'page' => $page,
            'titles' => $titles,
            'groups' => $groups,
            'favourites' => $user instanceof User ? $this->favouriteCodes() : null,
        ])
            ->layout('layouts.site', ['shell' => 'app', 'noindex' => false, 'page' => 'games', 'description' => $page['description']])
            ->title($page['meta'].' · Play online in Kenya');
    }
}

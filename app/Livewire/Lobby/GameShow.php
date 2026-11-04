<?php

namespace App\Livewire\Lobby;

use App\Games\GameCatalogue;
use App\Games\GameTitle;
use App\Games\Lobby;
use App\Livewire\Concerns\TogglesFavourites;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /games/{slug}: one slot or scratch card, crawlable, with a Play button into the in-site play page
 * and more titles like it. Only titles the catalogue offers have a page.
 */
class GameShow extends Component
{
    use TogglesFavourites;

    #[Locked]
    public string $slug;

    public function mount(GameCatalogue $catalogue, string $slug): void
    {
        abort_if($catalogue->titleBySlug($slug) === null, 404);

        $this->slug = $slug;
    }

    public function render(GameCatalogue $catalogue, Lobby $lobby): View
    {
        $title = $catalogue->titleBySlug($this->slug);
        abort_if($title === null, 404);

        $user = auth()->user();
        $favourites = $user instanceof User ? $this->favouriteCodes() : null;

        $more = $lobby->titlesOf($title->game)
            ->filter(fn (GameTitle $other) => $other->code !== $title->code
                && ($title->collection === null || $other->collection === $title->collection))
            ->take(6)
            ->values();

        $index = $title->game === 'scratch' ? ['Scratch cards', route('games.scratch')] : ['Slots', route('games.slots')];

        return view('livewire.lobby.game-show', [
            'title' => $title,
            'index' => $index,
            'more' => $more,
            'favourites' => $favourites,
            'description' => $this->describe($title),
        ])
            ->layout('layouts.site', ['shell' => 'app', 'noindex' => false, 'page' => 'games', 'og' => $title->game === 'scratch' ? 'og-scratch.jpg' : 'og-slots.jpg', 'description' => $this->describe($title)])
            ->title($title->name.' · Play online on Kadi');
    }

    private function describe(GameTitle $title): string
    {
        $kind = strtolower($title->typeLabel());
        $theme = $title->collection !== null ? ' from the '.$title->kindLabel().' collection' : '';
        $jackpot = $title->hasBadge('jackpot') ? ' with a jackpot' : '';

        return "{$title->name} is an online {$kind}{$jackpot}{$theme}. Play it on Kadi in your browser with your Kadi wallet, and deposit or withdraw with M-Pesa.";
    }
}

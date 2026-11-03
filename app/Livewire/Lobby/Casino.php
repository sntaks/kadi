<?php

namespace App\Livewire\Lobby;

use App\Games\Lobby;
use App\Livewire\Concerns\TogglesFavourites;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /casino and /casino/{filter}: every slot and scratch card, or one shelf of them (slots, scratch
 * cards, jackpot slots, favourites, a collection). A browse page for players, so not indexed: the
 * crawlable pages are /slots, /scratch-cards and /games/{slug}.
 */
class Casino extends Component
{
    use TogglesFavourites;

    #[Locked]
    public string $filter = 'all';

    public function mount(Lobby $lobby, ?string $filter = null): void
    {
        // "All games" lives at /casino only, so each shelf has one address.
        abort_if($filter !== null && ($filter === 'all' || ! array_key_exists($filter, $lobby->filters())), 404);

        $this->filter = $filter ?? 'all';
    }

    public function render(Lobby $lobby): View
    {
        $user = auth()->user();
        $user = $user instanceof User ? $user : null;
        $filters = $lobby->filters();

        return view('livewire.lobby.casino', [
            'filters' => $filters,
            'label' => $filters[$this->filter],
            'titles' => $lobby->filter($this->filter, $user) ?? collect(),
            'favourites' => $user ? $this->favouriteCodes() : null,
            'guest' => $user === null,
        ])
            ->layout('layouts.site', ['shell' => 'app', 'noindex' => true, 'page' => 'casino'])
            ->title(($this->filter === 'all' ? 'Casino' : $filters[$this->filter]).' · Kadi');
    }
}

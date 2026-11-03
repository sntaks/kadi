<?php

namespace App\Livewire\Account;

use App\Account\Wagers;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /activity (plan Phase 6): everything the player played. Kadi keeps its game history and dispute tools
 * (App\Livewire\Games\History, unchanged); slots and scratch cards list the player's wagers from KadiApi.
 * Sports joins once the sports pages exist.
 */
class Activity extends Component
{
    #[Url(as: 'game', except: 'kadi')]
    public string $tab = 'kadi';

    public int $page = 1;

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

    public function goTo(int $page): void
    {
        $this->page = max(1, $page);
    }

    /** @return array<string, string> */
    public function tabs(): array
    {
        return array_filter([
            'kadi' => 'Kadi',
            'slots' => 'Slots',
            'scratch' => 'Scratch cards',
            'sports' => config('platform.sports') ? 'Sports' : null,
        ]);
    }

    public function render(Wagers $wagers): View
    {
        $user = auth()->user();
        $list = null;

        if ($this->loaded && in_array($this->tab, ['slots', 'scratch'], true) && $user instanceof User) {
            $list = $wagers->page($user, $this->tab, $this->page);
        }

        return view('livewire.account.activity', ['tabs' => $this->tabs(), 'list' => $list])
            ->layout('layouts.app')
            ->title('Activity · Kadi');
    }
}

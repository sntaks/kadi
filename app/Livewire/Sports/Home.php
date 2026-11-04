<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\SportsLobby;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /sports (plan Phase 9): the jackpot banner, the competitions with matches open, and the full football list. A
 * shared slip opens from /sports?betslip=CODE.
 */
class Home extends Component
{
    #[Url(as: 'betslip', except: '')]
    public string $bookingCode = '';

    /** The list's first tab, from the menu's Today link (/sports?tab=today). */
    #[Url(except: 'highlights')]
    public string $tab = 'highlights';

    public function mount(): void
    {
        if ($this->bookingCode !== '') {
            $this->dispatch('load-booking-code', code: $this->bookingCode);
            $this->bookingCode = '';
        }
    }

    public function render(SportsLobby $lobby): View
    {
        return view('livewire.sports.home', [
            'competitions' => $lobby->competitions(),
            'jackpot' => $lobby->openJackpot(),
        ])
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports'])
            ->title('Football betting · Kadi');
    }
}

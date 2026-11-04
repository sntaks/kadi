<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * /sports/live: matches being played now, as a list only. Betting closes at kick-off until in-play trading exists,
 * so the page says so instead of showing prices (plan §2, honesty rules).
 */
class Live extends Component
{
    public function render(FixtureListing $listing): View
    {
        return view('livewire.sports.live', ['fixtures' => $listing->inPlay()])
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports'])
            ->title('In play · Kadi');
    }
}

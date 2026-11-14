<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Support\InPlay;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * /sports/live: matches being played now, with the live score. While in play is on, a covered match shows its live
 * 1X2/Winner prices (plan §4.7); the others say "Betting closed". Redrawn when the live tick broadcasts on
 * `sports.live`, with a 15-second poll as the fallback when Reverb is down.
 */
class Live extends Component
{
    /** A live tick refreshed scores or prices: redraw. */
    #[On('echo:sports.live,.fixtures.updated')]
    public function refreshLive(): void {}

    public function render(FixtureListing $listing): View
    {
        return view('livewire.sports.live', ['fixtures' => $listing->inPlay(), 'inPlayOn' => InPlay::on()])
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports', 'og' => 'og-sports.jpg'])
            ->title('In play · Kadi');
    }
}

<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\SportsLobby;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** /sports/{competition}: one competition's matches open for betting, beside its sport's sidebar. */
class CompetitionPage extends Component
{
    #[Locked]
    public int $competitionId;

    public function mount(Competition $competition): void
    {
        abort_unless($competition->is_synced && $competition->is_active_in_feed, 404);

        $this->competitionId = $competition->id;
    }

    public function render(): View
    {
        $competition = Competition::query()->with('sport')->findOrFail($this->competitionId);
        $profile = SportProfile::forSport($competition->sport);
        $lobby = app(SportsLobby::class);

        return view('livewire.sports.competition', [
            'competition' => $competition,
            'sport' => $profile->slug,
            'sportName' => $profile->name,
            'icon' => $profile->icon,
            'sports' => $lobby->sports(),
            'competitions' => $lobby->competitions($profile->slug),
        ])
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports', 'og' => 'og-sports.jpg'])
            ->title($competition->name.' betting · Kadi');
    }
}

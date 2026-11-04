<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Models\Competition;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** /sports/{competition}: one competition's matches open for betting. */
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
        $competition = Competition::query()->findOrFail($this->competitionId);

        return view('livewire.sports.competition', ['competition' => $competition])
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports'])
            ->title($competition->name.' betting · Kadi');
    }
}

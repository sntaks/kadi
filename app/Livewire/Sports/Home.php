<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\SportsLobby;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A sport's page: /sports (football) and /sports/{sport} (basketball...). Beside the list, the sport sidebar shows
 * the sport's leagues with their real counts (a chip row when the page is narrow). Football also shows the jackpot
 * banner. A shared slip opens from /sports?betslip=CODE.
 */
class Home extends Component
{
    #[Url(as: 'betslip', except: '')]
    public string $bookingCode = '';

    /** The list's first tab, from the sidebar's Today link (/sports?tab=today). */
    #[Url(except: 'highlights')]
    public string $tab = 'highlights';

    #[Locked]
    public string $sport = 'football';

    public function mount(?string $sport = null): void
    {
        $this->sport = $sport !== null && SportProfile::find($sport) !== null ? $sport : 'football';

        if ($this->bookingCode !== '') {
            $this->dispatch('load-booking-code', code: $this->bookingCode);
            $this->bookingCode = '';
        }
    }

    public function render(SportsLobby $lobby): View
    {
        $profile = SportProfile::find($this->sport);
        $name = $profile->name ?? 'Football';

        return view('livewire.sports.home', [
            'name' => $name,
            'icon' => $profile->icon ?? 'ball',
            'competitions' => $lobby->competitions($this->sport),
            'sports' => $lobby->sports(),
            'jackpot' => $this->sport === 'football' ? $lobby->openJackpot() : null,
        ])
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports', 'og' => 'og-sports.jpg'])
            ->title($name.' betting · Kadi');
    }
}

<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\FixtureListing;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/** /sports/search?q=: upcoming matches whose team or competition matches, from the top bar search. */
class Search extends Component
{
    #[Url]
    public string $q = '';

    public function render(FixtureListing $listing): View
    {
        return view('livewire.sports.search', ['fixtures' => $listing->search($this->q)])
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports', 'og' => 'og-sports.jpg'])
            ->title('Search matches · Kadi');
    }
}

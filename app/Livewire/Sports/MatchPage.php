<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\MarketTabs;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /sports/match/{fixture}: every market of one match. Lists open it in the match panel instead.
 *
 * @property-read Fixture $fixture
 * @property-read Collection<string, \Illuminate\Database\Eloquent\Collection<int, Market>> $marketsByTab
 */
class MatchPage extends Component
{
    #[Locked]
    public int $fixtureId;

    #[Url(except: 'main')]
    public string $tab = 'main';

    public function mount(Fixture $fixture): void
    {
        abort_unless($fixture->competition->is_synced, 404);

        $this->fixtureId = $fixture->id;
        $this->updated();
    }

    public function updated(): void
    {
        $this->tab = array_key_exists($this->tab, MarketTabs::TABS) ? $this->tab : 'main';
    }

    #[Computed]
    public function fixture(): Fixture
    {
        return Fixture::query()->with('competition')->findOrFail($this->fixtureId);
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Market>> Markets per tab, empty tabs left out.
     */
    #[Computed]
    public function marketsByTab(): Collection
    {
        return MarketTabs::group($this->fixture->markets()->with('selections')->get());
    }

    public function render(): View
    {
        return view('livewire.sports.match')
            ->layout('layouts.site', ['shell' => 'app', 'rail' => true, 'noindex' => true, 'page' => 'sports'])
            ->title($this->fixture->name().' · Kadi');
    }
}

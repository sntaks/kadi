<?php

namespace App\Livewire\Sports;

use App\Sportsbook\Catalogue\MarketTabs;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A match's markets in a panel over the current page, opened from match links on the lists and the bet slip. The
 * address bar shows the match page while it is open, so a shared or reloaded link opens the full page.
 *
 * @property-read ?Fixture $fixture
 * @property-read Collection<string, \Illuminate\Database\Eloquent\Collection<int, Market>> $marketsByTab
 */
class MatchPanel extends Component
{
    #[Locked]
    public ?int $fixtureId = null;

    public string $tab = 'main';

    public function show(int $fixtureId): void
    {
        $this->fixtureId = Fixture::query()
            ->whereKey($fixtureId)
            ->whereHas('competition', fn ($query) => $query->where('is_synced', true))
            ->value('id');
        $this->tab = 'main';
        unset($this->fixture, $this->marketsByTab);
    }

    public function updatedTab(): void
    {
        $this->tab = array_key_exists($this->tab, MarketTabs::TABS) ? $this->tab : 'main';
    }

    #[Computed]
    public function fixture(): ?Fixture
    {
        return $this->fixtureId === null ? null : Fixture::query()->with('competition')->find($this->fixtureId);
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Market>>
     */
    #[Computed]
    public function marketsByTab(): Collection
    {
        return $this->fixture === null ? collect() : MarketTabs::group($this->fixture->markets()->with('selections')->get()->each->setRelation('fixture', $this->fixture));
    }

    public function render(): View
    {
        return view('livewire.sports.match-panel');
    }
}

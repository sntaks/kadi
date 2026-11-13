<?php

namespace App\Livewire\Predict;

use App\Predict\Data\Market;
use App\Predict\Markets;
use App\Support\PredictPages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A market in a sheet over the current page, opened from the lobby, the landing and related links, like the sports
 * match panel. The address bar shows the market page while it is open, so a shared or reloaded link opens the full
 * page (which has the price chart). Fast by design: the market comes from the copy the list already primed
 * (Markets::list()), so opening it usually needs no call to the API.
 */
class MarketPanel extends Component
{
    #[Locked]
    public ?int $marketId = null;

    public function show(int $marketId): void
    {
        $this->marketId = $marketId;
    }

    public function render(Markets $markets): View
    {
        $market = $this->marketId !== null ? $markets->find($this->marketId) : null;

        return view('livewire.predict.market-panel', [
            'market' => $market,
            'criteria' => $market instanceof Market ? MarketPage::criteria($market) : null,
            'canBet' => PredictPages::bettingFor(auth()->user()),
            'bettingOn' => PredictPages::bettingOn(),
            'now' => Date::now(),
        ]);
    }
}

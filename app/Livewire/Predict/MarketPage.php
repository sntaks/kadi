<?php

namespace App\Livewire\Predict;

use App\Predict\Data\Market;
use App\Predict\MarketFeed;
use App\Predict\Markets;
use App\Support\PredictPages;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Date;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * /predictions/{market}: the question, both sides with their odds, when betting closes (or why it cannot be bet
 * now), the price chart, what decides it and where the market comes from. The bet slip joins in Phase 4. Live
 * figures come from the feed's broadcast; the 25 s poll re-renders only when the feed has moved.
 */
class MarketPage extends Component
{
    #[Locked]
    public int $marketId;

    #[Locked]
    public int $seenVersion = 0;

    /**
     * The price history is the slowest read (about a second from the API): the page renders without it and asks for
     * it straight after (wire:init), so the question, the odds and the rules never wait on it.
     */
    #[Locked]
    public bool $withChart = false;

    public function loadChart(): void
    {
        $this->withChart = true;
    }

    /** The side picked on the lobby (?side=yes), handed to the slip. */
    #[Locked]
    public ?string $side = null;

    public function mount(int $market, Markets $markets): void
    {
        abort_if($markets->find($market) === null, 404);

        $this->marketId = $market;
        $side = request()->query('side');
        $this->side = in_array($side, Market::SIDES, true) ? $side : null;
    }

    public function sync(MarketFeed $feed): void
    {
        if ($feed->version() === $this->seenVersion) {
            $this->skipRender();
        }
    }

    public function render(Markets $markets, MarketFeed $feed): View
    {
        $this->seenVersion = $feed->version();
        $market = $markets->find($this->marketId);

        // Gone since the page opened (unpublished, or the API is down with no copy left).
        abort_if($market === null, 404);

        return view('livewire.predict.market', [
            'market' => $market,
            'history' => $this->withChart ? $markets->history($market->id) : null,
            'stale' => $feed->isStale(),
            'canBet' => PredictPages::bettingFor(auth()->user()),
            'bettingOn' => PredictPages::bettingOn(),
            'now' => Date::now(),
            'criteria' => self::criteria($market),
            'related' => self::related($market, $markets),
        ])
            ->layout('layouts.site', ['shell' => 'app', 'noindex' => true, 'page' => 'predictions', 'predictPanel' => true])
            ->title($market->question.' · Kadi');
    }

    /**
     * Up to four other markets to look at: the same event first, then the same category. From the cached list, so it
     * costs no extra call most of the time.
     *
     * @return list<Market>
     */
    public static function related(Market $market, Markets $markets): array
    {
        $others = array_filter(
            $markets->list(['category' => $market->category, 'source' => $market->source])['markets'],
            fn (Market $other) => $other->id !== $market->id,
        );

        usort($others, fn (Market $a, Market $b) => (int) ($b->eventTitle !== null && $b->eventTitle === $market->eventTitle)
            <=> (int) ($a->eventTitle !== null && $a->eventTitle === $market->eventTitle));

        return array_slice($others, 0, 4);
    }

    /**
     * What decides the market, shown once: imported markets repeat the description as their criteria.
     */
    public static function criteria(Market $market): ?string
    {
        $criteria = $market->resolutionCriteria;

        return $criteria !== null && trim($criteria) !== trim((string) $market->description) ? $criteria : null;
    }
}

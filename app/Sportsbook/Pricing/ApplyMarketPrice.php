<?php

namespace App\Sportsbook\Pricing;

use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;

/**
 * Writes a priced market to a fixture: feed prices, fair probabilities, a history snapshot whenever the offered
 * price changes, and the resulting market status.
 *
 * Admin overrides are never touched, a manually suspended market stays suspended, and a fixture that has kicked
 * off is never reopened. A market suspended for too few bookmakers or stale odds reopens once it is priced again.
 */
class ApplyMarketPrice
{
    /**
     * @param  array<string, string>  $selectionNames  Outcome => name shown to players, e.g. ['draw' => 'Draw'].
     * @param  bool  $createWhenUnpriced  Whether to create the market (suspended) when it cannot be priced yet.
     */
    public function handle(
        Fixture $fixture,
        MarketType $type,
        ?float $line,
        PricedMarket $priced,
        array $selectionNames,
        bool $createWhenUnpriced,
        MarketSource $source = MarketSource::Feed,
    ): ?Market {
        $key = Market::keyFor($type, $line);
        $market = $fixture->markets()->where('key', $key)->first();

        if ($market === null && ! $priced->isPriced() && ! $createWhenUnpriced) {
            return null;
        }

        // A line owned by the other source (e.g. a derived Over 2.5 the feed now prices too thinly) is left alone
        // unless this source can actually price it.
        if ($market !== null && $market->source !== $source && ! $priced->isPriced()) {
            return $market;
        }

        $market ??= new Market([
            'fixture_id' => $fixture->id,
            'type' => $type,
            'line' => $line,
            'key' => $key,
            'source' => $source,
            'status' => MarketStatus::Suspended,
        ]);

        $market->bookmaker_count = $priced->bookmakerCount;

        if (! $priced->isPriced()) {
            if (! $market->exists || $market->isOpen()) {
                $market->status = MarketStatus::Suspended;
                $market->suspension_reason = $priced->suspensionReason;
            }

            $market->save();

            return $market;
        }

        $isNew = ! $market->exists;
        // Feed prices take over a line we had been deriving; DeriveMarkets never replaces a feed-owned line.
        $market->source = $source;
        $market->priced_at = now('UTC');
        $market->save();

        // One query for the market's selections, not one per outcome (correct score alone has 26).
        $selections = $isNew ? collect() : $market->selections()->get()->keyBy('outcome');

        foreach ($priced->outcomes as $outcome => $price) {
            $this->writeSelection($market, $selections->get($outcome), $outcome, $selectionNames[$outcome] ?? $outcome, $price);
        }

        $this->updateStatus($market, $fixture, $isNew);

        return $market;
    }

    /**
     * @param  array{fair_probability: float, price: string}  $price
     */
    private function writeSelection(Market $market, ?Selection $selection, string $outcome, string $name, array $price): void
    {
        $selection ??= $market->selections()->make(['outcome' => $outcome]);
        $offeredBefore = $selection->exists ? $selection->price() : null;

        $selection->fill([
            'name' => $name,
            'fair_probability' => $price['fair_probability'],
            'feed_price' => $price['price'],
        ])->save();

        // The decimal casts format feed_price on read, so the comparison needs no reload.
        $selection->recordOfferedPriceChange($offeredBefore);
    }

    private function updateStatus(Market $market, Fixture $fixture, bool $isNew): void
    {
        if ($fixture->hasKickedOff()) {
            if ($market->isOpen() || $market->suspension_reason !== SuspensionReason::KickedOff) {
                $market->suspend(SuspensionReason::KickedOff);
            }

            return;
        }

        if ($market->isOpen()) {
            return;
        }

        if ($isNew || $market->suspension_reason?->clearsOnReprice()) {
            $market->reopen();
        }
    }
}

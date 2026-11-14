<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\Pricing\ApplyMarketPrice;
use App\Sportsbook\Pricing\ConsensusPricer;
use App\Sportsbook\Support\SportProfile;

/**
 * Prices one match's bulk feed markets (1X2/Winner, goal or points lines, handicaps) from one odds answer. Used by
 * the daily sync with the pre-match margin and by the live sync with the in-play margin and the live status rules.
 *
 * @phpstan-import-type EventOddsData from OddsApi
 */
class PriceFeedEvent
{
    private ConsensusPricer $pricer;

    private bool $live = false;

    public function __construct(private readonly ApplyMarketPrice $applyMarketPrice) {}

    /**
     * @param  EventOddsData  $event
     * @param  list<MarketType>  $marketTypes
     */
    public function handle(Fixture $fixture, array $event, array $marketTypes, ConsensusPricer $pricer, SportProfile $profile, bool $live = false): void
    {
        [$this->pricer, $this->live] = [$pricer, $live];

        foreach ($marketTypes as $type) {
            match ($type) {
                MarketType::MatchResult => $this->priceMatchResult($fixture, $event, $profile),
                MarketType::Totals => $this->priceTotals($fixture, $event),
                MarketType::Spreads => $this->priceSpreads($fixture, $event),
                default => null,
            };
        }
    }

    /**
     * @param  EventOddsData  $event
     */
    private function priceMatchResult(Fixture $fixture, array $event, SportProfile $profile): void
    {
        // A sport without a draw (basketball) has a two-way Winner incl. overtime: the feed's h2h has no Draw outcome.
        $names = $profile->hasDraw
            ? ['home' => $event['home_team'], 'draw' => 'Draw', 'away' => $event['away_team']]
            : ['home' => $event['home_team'], 'away' => $event['away_team']];
        $outcomeByName = array_flip($names);
        $bookmakerPrices = [];

        foreach (LinePrices::outcomesByBook($event, MarketType::MatchResult->value) as $bookmakerKey => $outcomes) {
            foreach ($outcomes as $outcome) {
                if (isset($outcomeByName[$outcome['name']])) {
                    $bookmakerPrices[$bookmakerKey][$outcomeByName[$outcome['name']]] = (float) $outcome['price'];
                }
            }
        }

        $this->applyMarketPrice->handle(
            $fixture,
            MarketType::MatchResult,
            null,
            $this->pricer->price($bookmakerPrices, $profile->matchResultOutcomes()),
            $names,
            createWhenUnpriced: true,
            live: $this->live,
        );
    }

    /**
     * Each goal line (2.5, 3.5, ...) is its own market; only lines enough bookmakers price are created. Quarter
     * lines (2.25, 2.75) are skipped: they half win or half lose, which we don't settle yet.
     *
     * @param  EventOddsData  $event
     */
    private function priceTotals(Fixture $fixture, array $event): void
    {
        foreach (LinePrices::overUnder(LinePrices::outcomesByBook($event, MarketType::Totals->value)) as $line => $bookmakerPrices) {
            $this->applyMarketPrice->handle(
                $fixture,
                MarketType::Totals,
                (float) $line,
                $this->pricer->price($bookmakerPrices, ['over', 'under']),
                ['over' => "Over {$line}", 'under' => "Under {$line}"],
                createWhenUnpriced: false,
                live: $this->live,
            );
        }
    }

    /**
     * Handicaps (Asian handicap, the feed's `spreads`). Each line is its own market, keyed by the home side's
     * handicap: line -1.5 is "home -1.5 / away +1.5". A bookmaker counts for a line only when its two sides mirror
     * each other. Quarter lines are skipped, as for totals. Only lines enough bookmakers price are created.
     *
     * @param  EventOddsData  $event
     */
    private function priceSpreads(Fixture $fixture, array $event): void
    {
        $outcomes = LinePrices::outcomesByBook($event, MarketType::Spreads->value);

        foreach (LinePrices::handicaps($outcomes, $event['home_team'], $event['away_team']) as $line => $bookmakerPrices) {
            $this->applyMarketPrice->handle(
                $fixture,
                MarketType::Spreads,
                (float) $line,
                $this->pricer->price($bookmakerPrices, ['home', 'away']),
                [
                    'home' => $event['home_team'].' '.Market::formatLine($line, signed: true),
                    'away' => $event['away_team'].' '.Market::formatLine(-(float) $line, signed: true),
                ],
                createWhenUnpriced: false,
                live: $this->live,
            );
        }
    }
}

<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\Pricing\ApplyMarketPrice;
use App\Sportsbook\Pricing\ConsensusPricer;
use App\Sportsbook\Pricing\Derived\DeriveMarkets;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Buys one competition's odds (one credit per market type per region) and prices every fixture in it.
 *
 * @phpstan-import-type EventOddsData from OddsApi
 */
class SyncOdds
{
    public function __construct(
        private readonly OddsApi $oddsApi,
        private readonly ConsensusPricer $pricer,
        private readonly UpsertFixture $upsertFixture,
        private readonly ApplyMarketPrice $applyMarketPrice,
        private readonly DeriveMarkets $deriveMarkets,
    ) {}

    /**
     * @param  list<MarketType>  $marketTypes
     * @return int The number of fixtures in the response.
     */
    public function handle(Competition $competition, array $marketTypes): int
    {
        $events = $this->oddsApi->odds(
            $competition->odds_api_key,
            array_map(fn (MarketType $type): string => $type->value, $marketTypes),
        );

        $fixtures = [];
        // Every fixture's markets and selections in three queries, not one per market (Sentry KADI-B/KADI-C).
        $existing = $this->upsertFixture->existing($events, ['markets.selections']);

        foreach ($events as $event) {
            $fixtures[] = DB::transaction(function () use ($competition, $event, $marketTypes, $existing): Fixture {
                $fixture = $this->upsertFixture->handle($competition, $event, $existing);

                if (! $fixture->relationLoaded('markets')) {
                    $fixture->setRelation('markets', new EloquentCollection);
                }

                foreach ($marketTypes as $type) {
                    match ($type) {
                        MarketType::MatchResult => $this->priceMatchResult($fixture, $event, $this->profile($competition)),
                        MarketType::Totals => $this->priceTotals($fixture, $event),
                        default => null,
                    };
                }

                return $fixture;
            });
        }

        // Derive only once every fixture's feed prices are in, so the competition's typical goals (from its feed
        // Over 2.5 lines) is complete before any fixture relies on it. Only football derives markets.
        if (in_array(MarketType::MatchResult, $marketTypes, true) && $this->profile($competition)->derivedMarkets) {
            foreach ($fixtures as $fixture) {
                // Deriving reads the markets from the database, as it always has.
                $fixture->unsetRelation('markets');
                // Every fixture here is in this competition: saves one query per fixture in DeriveMarkets.
                $fixture->setRelation('competition', $competition);
                DB::transaction(fn () => $this->deriveMarkets->handle($fixture));
            }
        }

        return count($events);
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

        foreach ($this->marketsOfType($event, MarketType::MatchResult) as $bookmakerKey => $outcomes) {
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
        );
    }

    private function profile(Competition $competition): SportProfile
    {
        return SportProfile::forSport($competition->sport);
    }

    /**
     * Each goal line (2.5, 3.5, ...) is its own market; only lines enough bookmakers price are created.
     *
     * @param  EventOddsData  $event
     */
    private function priceTotals(Fixture $fixture, array $event): void
    {
        $pricesByLine = [];

        foreach ($this->marketsOfType($event, MarketType::Totals) as $bookmakerKey => $outcomes) {
            foreach ($outcomes as $outcome) {
                $side = strtolower($outcome['name']);

                if (isset($outcome['point']) && in_array($side, ['over', 'under'], true)) {
                    $pricesByLine[(string) (float) $outcome['point']][$bookmakerKey][$side] = (float) $outcome['price'];
                }
            }
        }

        foreach ($pricesByLine as $line => $bookmakerPrices) {
            $this->applyMarketPrice->handle(
                $fixture,
                MarketType::Totals,
                (float) $line,
                $this->pricer->price($bookmakerPrices, ['over', 'under']),
                ['over' => "Over {$line}", 'under' => "Under {$line}"],
                createWhenUnpriced: false,
            );
        }
    }

    /**
     * Each bookmaker's outcomes for one market type.
     *
     * @param  EventOddsData  $event
     * @return array<string, list<array{name: string, price: float|int, point?: float|int}>>
     */
    private function marketsOfType(array $event, MarketType $type): array
    {
        $markets = [];

        foreach ($event['bookmakers'] as $bookmaker) {
            foreach ($bookmaker['markets'] as $market) {
                if ($market['key'] === $type->value) {
                    $markets[$bookmaker['key']] = $market['outcomes'];
                }
            }
        }

        return $markets;
    }
}

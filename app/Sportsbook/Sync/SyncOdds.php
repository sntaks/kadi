<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\Pricing\ConsensusPricer;
use App\Sportsbook\Pricing\Derived\DeriveMarkets;
use App\Sportsbook\Support\InPlay;
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
        private readonly PriceFeedEvent $priceFeedEvent,
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

                // A match in play in an in-play competition is the live sync's while in play is on: pricing it here
                // would close its live markets as kicked off until the next live tick.
                if ($fixture->hasKickedOff() && InPlay::on() && InPlay::coversCompetition($competition)) {
                    return $fixture;
                }

                $this->priceFeedEvent->handle($fixture, $event, $marketTypes, $this->pricer, $this->profile($competition));

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

    private function profile(Competition $competition): SportProfile
    {
        return SportProfile::forSport($competition->sport);
    }
}

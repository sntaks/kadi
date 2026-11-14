<?php

namespace App\Sportsbook\Sync;

use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Enums\SuspensionReason;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\Pricing\ApplyMarketPrice;
use App\Sportsbook\Pricing\ConsensusPricer;
use Illuminate\Support\Facades\DB;

/**
 * Buys one match's extra markets from the event endpoint (plan §10.6) and prices each line: alternate goal lines and
 * handicaps join the bulk feed's main line as more `totals:*` / `spreads:*` markets, and team goal lines become
 * `home_team_totals:*` / `away_team_totals:*`. The bulk feed owns its lines, so a key it priced is never touched here.
 * Extra lines the API stops offering are suspended until a later answer prices them again.
 *
 * @phpstan-import-type EventOddsData from OddsApi
 */
class SyncFixtureExtras
{
    /** Feed market key => the market types it prices. */
    public const MARKETS = [
        'alternate_totals' => [MarketType::Totals],
        'alternate_spreads' => [MarketType::Spreads],
        'alternate_team_totals' => [MarketType::HomeTeamTotals, MarketType::AwayTeamTotals],
    ];

    public function __construct(
        private readonly OddsApi $oddsApi,
        private readonly ConsensusPricer $pricer,
        private readonly ApplyMarketPrice $applyMarketPrice,
    ) {}

    /**
     * @param  list<string>  $markets  Feed market keys, from self::MARKETS.
     * @return int The number of lines priced (0 when the API has no extras for the match).
     */
    public function handle(Fixture $fixture, array $markets): int
    {
        $markets = array_values(array_intersect($markets, array_keys(self::MARKETS)));
        $fixture->loadMissing('competition');
        $event = $markets === [] ? null : $this->oddsApi->eventOdds($fixture->competition->odds_api_key, $fixture->external_id, $markets);

        return DB::transaction(function () use ($fixture, $markets, $event): int {
            $fixture->load('markets');
            $priced = $event === null ? [] : $this->price($fixture, $event, $markets);
            $this->suspendMissing($fixture, $markets, $priced);
            $fixture->update(['extras_synced_at' => now('UTC')]);

            return count($priced);
        });
    }

    /**
     * @param  EventOddsData  $event
     * @param  list<string>  $markets
     * @return list<string> The keys written.
     */
    private function price(Fixture $fixture, array $event, array $markets): array
    {
        $written = [];
        [$home, $away] = [$event['home_team'], $event['away_team']];

        foreach ($markets as $key) {
            $outcomes = LinePrices::outcomesByBook($event, $key);

            $lines = match ($key) {
                'alternate_totals' => [MarketType::Totals->value => LinePrices::overUnder($outcomes)],
                'alternate_spreads' => [MarketType::Spreads->value => LinePrices::handicaps($outcomes, $home, $away)],
                'alternate_team_totals' => [
                    MarketType::HomeTeamTotals->value => LinePrices::overUnder($outcomes, $home),
                    MarketType::AwayTeamTotals->value => LinePrices::overUnder($outcomes, $away),
                ],
                default => [],
            };

            foreach ($lines as $type => $pricesByLine) {
                $type = MarketType::from($type);

                foreach ($pricesByLine as $line => $bookmakerPrices) {
                    $marketKey = Market::keyFor($type, (float) $line);

                    // The bulk feed's main line stays the bulk feed's.
                    if ($fixture->markets->firstWhere('key', $marketKey)?->source === MarketSource::Feed) {
                        continue;
                    }

                    [$outcomeKeys, $names] = $type === MarketType::Spreads
                        ? [['home', 'away'], ['home' => "{$home} ".Market::formatLine($line, signed: true), 'away' => "{$away} ".Market::formatLine(-(float) $line, signed: true)]]
                        : [['over', 'under'], ['over' => "Over {$line}", 'under' => "Under {$line}"]];

                    $priced = $this->pricer->price($bookmakerPrices, $outcomeKeys);

                    $this->applyMarketPrice->handle(
                        $fixture,
                        $type,
                        (float) $line,
                        $priced,
                        $names,
                        createWhenUnpriced: false,
                        source: MarketSource::EventFeed,
                    );

                    if ($priced->isPriced()) {
                        $written[] = $marketKey;
                    }
                }
            }
        }

        return $written;
    }

    /**
     * Extra lines this answer no longer prices are suspended, so a line the books dropped is never sold at its old
     * price. They reopen when a later answer prices them (the reason clears on reprice).
     *
     * @param  list<string>  $markets
     * @param  list<string>  $priced
     */
    private function suspendMissing(Fixture $fixture, array $markets, array $priced): void
    {
        $types = collect($markets)->flatMap(fn (string $key): array => self::MARKETS[$key])->all();

        $fixture->markets
            ->filter(fn (Market $market): bool => $market->source === MarketSource::EventFeed
                && in_array($market->type, $types, true)
                && ! in_array($market->key, $priced, true)
                && $market->isOpen())
            ->each(fn (Market $market) => $market->suspend(SuspensionReason::InsufficientBookmakers));
    }
}

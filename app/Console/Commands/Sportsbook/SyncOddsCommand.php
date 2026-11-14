<?php

namespace App\Console\Commands\Sportsbook;

use App\Jobs\Sportsbook\SyncCompetitionOdds;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\OddsApi\OddsApiSource;
use App\Sportsbook\Support\SportProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('sportsbook:sync-odds
    {--markets= : Comma-separated market keys for every competition (defaults to each sport\'s markets, config sportsbook.sports)}
    {--sync : Run in this process instead of queueing a job per competition}')]
#[Description('Buy and apply odds for synced competitions with fixtures coming up (spends Odds API credits)')]
class SyncOddsCommand extends Command
{
    public function handle(CreditBudget $budget): int
    {
        $option = $this->option('markets');
        $marketTypes = is_string($option) && $option !== '' ? $this->marketTypes($option) : [];

        if ($marketTypes === null) {
            $feedKeys = array_map(fn (MarketType $type): string => $type->value, array_filter(MarketType::cases(), fn (MarketType $type): bool => $type->isFeedMarket()));
            $this->error('Unknown market key. Use any of: '.implode(', ', $feedKeys).'.');

            return self::FAILURE;
        }

        $competitions = Competition::query()
            ->synced()
            ->whereHas('fixtures', fn (Builder $query) => $query
                ->upcoming()
                ->where('kicks_off_at', '<=', now('UTC')->addDays((int) config('sportsbook.odds_api.lookahead_days'))))
            ->with('sport')
            ->orderBy('sort_order')
            ->get();

        // Each sport buys its own markets (football h2h; basketball h2h + totals) unless --markets says otherwise.
        $marketsFor = fn (Competition $competition): array => $marketTypes !== [] ? $marketTypes : SportProfile::forSport($competition->sport)->markets;

        $estimatedCredits = $competitions->sum(fn (Competition $competition): int => OddsApiSource::creditsFor(
            array_map(fn (MarketType $type): string => $type->value, $marketsFor($competition)),
            $competition->odds_api_key,
        ));

        $cost = config('sportsbook.odds_api.driver') === 'live'
            ? "about {$estimatedCredits} credits"
            : 'no credits (replay driver)';

        $allowance = $budget->allowance(OddsApiPurpose::PreMatch);
        $this->line("Pricing {$competitions->count()} competitions for {$cost}"
            .' ('.$budget->spentThisMonth().' spent this month, '.$budget->spentThisMonth(OddsApiPurpose::PreMatch).' on pre-match'
            .($allowance !== null ? " of {$allowance}" : '').', '.($budget->remaining() ?? 'unknown').' remaining).');

        foreach ($competitions as $competition) {
            $this->option('sync')
                ? SyncCompetitionOdds::dispatchSync($competition, $marketsFor($competition))
                : SyncCompetitionOdds::dispatch($competition, $marketsFor($competition));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<MarketType>|null Null when a key is not a known market type.
     */
    private function marketTypes(string $keys): ?array
    {
        $types = [];

        foreach (array_filter(array_map('trim', explode(',', $keys))) as $key) {
            $type = MarketType::tryFrom($key);

            if ($type === null || ! $type->isFeedMarket()) {
                return null;
            }

            $types[] = $type;
        }

        return $types === [] ? null : $types;
    }
}

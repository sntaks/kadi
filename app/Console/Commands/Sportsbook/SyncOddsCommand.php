<?php

namespace App\Console\Commands\Sportsbook;

use App\Jobs\Sportsbook\SyncCompetitionOdds;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\OddsApi\CreditBudget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('sportsbook:sync-odds
    {--markets= : Comma-separated market keys (defaults to ODDS_API_MARKETS)}
    {--sync : Run in this process instead of queueing a job per competition}')]
#[Description('Buy and apply odds for synced competitions with fixtures coming up (spends Odds API credits)')]
class SyncOddsCommand extends Command
{
    public function handle(CreditBudget $budget): int
    {
        $marketTypes = $this->marketTypes();

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
            ->orderBy('sort_order')
            ->get();

        $regionCount = count(explode(',', (string) config('sportsbook.odds_api.region')));
        $estimatedCredits = $competitions->count() * count($marketTypes) * $regionCount;

        $cost = config('sportsbook.odds_api.driver') === 'live'
            ? "about {$estimatedCredits} credits"
            : 'no credits (replay driver)';

        $this->line("Pricing {$competitions->count()} competitions for {$cost}"
            .' ('.$budget->spentThisMonth().' spent this month, '.($budget->remaining() ?? 'unknown').' remaining).');

        foreach ($competitions as $competition) {
            $this->option('sync')
                ? SyncCompetitionOdds::dispatchSync($competition, $marketTypes)
                : SyncCompetitionOdds::dispatch($competition, $marketTypes);
        }

        return self::SUCCESS;
    }

    /**
     * @return list<MarketType>|null Null when a key is not a known market type.
     */
    private function marketTypes(): ?array
    {
        $option = $this->option('markets');
        $keys = is_string($option) && $option !== '' ? $option : (string) config('sportsbook.odds_api.markets');
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

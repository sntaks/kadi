<?php

namespace App\Console\Commands\Sportsbook;

use App\Jobs\Sportsbook\SyncFixtureExtrasJob;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\OddsApi\CreditBudget;
use App\Sportsbook\OddsApi\OddsApiPurpose;
use App\Sportsbook\OddsApi\OddsApiSource;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('sportsbook:sync-event-odds
    {--sync : Run in this process instead of queueing a job per match}')]
#[Description('Buy the extra markets of matches due in a sync window (spends Odds API credits, plan §10.6)')]
class SyncEventOddsCommand extends Command
{
    public function handle(CreditBudget $budget): int
    {
        if (! config('sportsbook.per_event.enabled')) {
            $this->line('Match extras are switched off (SPORTSBOOK_PER_EVENT_ENABLED).');

            return self::SUCCESS;
        }

        /** @var list<string> $markets */
        $markets = (array) config('sportsbook.per_event.markets');
        $due = $this->due();
        $cost = config('sportsbook.odds_api.driver') === 'live'
            ? 'at most '.($due->count() * count($markets) * OddsApiSource::forEventOdds()->creditsPerMarket()).' credits'
            : 'no credits (replay driver)';
        $allowance = $budget->allowance(OddsApiPurpose::PerEvent);

        $this->line("Buying extras for {$due->count()} matches for {$cost} ("
            .$budget->spentThisMonth(OddsApiPurpose::PerEvent).' spent on extras this month'.($allowance !== null ? " of {$allowance}" : '').').');

        foreach ($due as $fixture) {
            $this->option('sync')
                ? SyncFixtureExtrasJob::dispatchSync($fixture, $markets)
                : SyncFixtureExtrasJob::dispatch($fixture, $markets);
        }

        return self::SUCCESS;
    }

    /**
     * Upcoming matches in the extras competitions whose kick-off is inside a window and that have not been bought
     * since they entered it. A window is [from, to] hours before kick-off.
     *
     * @return Collection<int, Fixture>
     */
    private function due(): Collection
    {
        /** @var list<array{0: int|float, 1: int|float}> $windows */
        $windows = (array) config('sportsbook.per_event.windows');
        $now = now('UTC');

        return Fixture::query()
            ->upcoming()
            ->whereHas('competition', fn (Builder $query) => $query->synced()->whereIn('odds_api_key', (array) config('sportsbook.per_event.competitions')))
            ->where(function (Builder $query) use ($windows, $now) {
                foreach ($windows as [$from, $to]) {
                    $query->orWhereBetween('kicks_off_at', [$now->copy()->addMinutes((int) round($from * 60)), $now->copy()->addMinutes((int) round($to * 60))]);
                }
            })
            ->with('competition')
            ->orderBy('kicks_off_at')
            ->get()
            ->filter(function (Fixture $fixture) use ($windows, $now): bool {
                foreach ($windows as [$from, $to]) {
                    $opensAt = $fixture->kicks_off_at->subMinutes((int) round($to * 60));
                    $closesAt = $fixture->kicks_off_at->subMinutes((int) round($from * 60));

                    if ($now->betweenIncluded($opensAt, $closesAt)) {
                        return $fixture->extras_synced_at === null || $fixture->extras_synced_at->lt($opensAt);
                    }
                }

                return false;
            })
            ->values();
    }
}

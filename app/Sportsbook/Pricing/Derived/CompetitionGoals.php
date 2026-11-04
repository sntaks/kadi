<?php

namespace App\Sportsbook\Pricing\Derived;

use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;

/**
 * The typical expected goals for a competition's matches: the median implied by its fixtures' feed Over 2.5
 * prices, falling back to the configured default when the competition has none. Remembered per instance, so one
 * odds sync works it out once per competition.
 */
class CompetitionGoals
{
    /**
     * @var array<int, float>
     */
    private array $remembered = [];

    public function forCompetition(Competition $competition): float
    {
        return $this->remembered[$competition->id] ??= $this->median($competition) ?? (float) config('sportsbook.odds.default_total_goals');
    }

    private function median(Competition $competition): ?float
    {
        $totals = Selection::query()
            ->where('outcome', 'over')
            ->whereNotNull('fair_probability')
            ->whereHas('market', fn ($markets) => $markets
                ->where('source', MarketSource::Feed)
                ->where('key', Market::keyFor(MarketType::Totals, 2.5))
                ->whereHas('fixture', fn ($fixtures) => $fixtures
                    ->where('competition_id', $competition->id)
                    ->where('kicks_off_at', '>', now('UTC')->subDays(14))))
            ->pluck('fair_probability')
            ->map(fn (mixed $over): float => GoalsModel::totalGoalsForOverTwoAndAHalf((float) $over))
            ->sort()
            ->values();

        if ($totals->isEmpty()) {
            return null;
        }

        $middle = intdiv($totals->count(), 2);

        return $totals->count() % 2 === 1 ? $totals[$middle] : ($totals[$middle - 1] + $totals[$middle]) / 2;
    }
}

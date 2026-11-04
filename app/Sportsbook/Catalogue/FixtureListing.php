<?php

namespace App\Sportsbook\Catalogue;

use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * The fixture lists players browse. Each list eager-loads the one market shown in the list column and counts the
 * rest, so rendering a list never queries per fixture.
 */
class FixtureListing
{
    /**
     * Market keys players can switch the list column to, with their labels.
     *
     * @var array<string, string>
     */
    public const LIST_MARKETS = [
        'h2h' => '1X2',
        'double_chance' => 'Double Chance',
        'totals:2.5' => 'Over/Under 2.5',
        'btts' => 'Both Teams To Score',
    ];

    /**
     * Tabs on the home page, with their labels.
     *
     * @var array<string, string>
     */
    public const TABS = [
        'highlights' => 'Highlights',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'upcoming' => 'Upcoming',
    ];

    private const LIMIT = 60;

    /**
     * @return Collection<int, Fixture>
     */
    public function forTab(string $tab, string $marketKey, ?Competition $competition = null): Collection
    {
        $localNow = CarbonImmutable::now(config('sportsbook.timezone'));

        $query = $this->bettable($marketKey, $competition);

        match ($tab) {
            'today' => $query->where('kicks_off_at', '<=', $localNow->endOfDay()->utc()),
            'tomorrow' => $query
                ->where('kicks_off_at', '>=', $localNow->addDay()->startOfDay()->utc())
                ->where('kicks_off_at', '<=', $localNow->addDay()->endOfDay()->utc()),
            'highlights' => $query->where('kicks_off_at', '<=', now('UTC')->addDays(7)),
            default => $query->where('kicks_off_at', '<=', now('UTC')->addDays((int) config('sportsbook.odds_api.lookahead_days'))),
        };

        if ($tab === 'highlights') {
            $query->reorder()
                ->orderBy(Competition::select('sort_order')->whereColumn('sb_competitions.id', 'sb_fixtures.competition_id'))
                ->orderBy('kicks_off_at');
        }

        return $query->limit(self::LIMIT)->get();
    }

    /**
     * Fixtures in play: kicked off recently and still listed by the feed. Betting on them is closed.
     *
     * @return Collection<int, Fixture>
     */
    public function inPlay(): Collection
    {
        return Fixture::query()
            ->with('competition')
            ->where('status', FixtureStatus::Scheduled)
            ->whereBetween('kicks_off_at', [now('UTC')->subMinutes((int) config('sportsbook.settlement.result_after_minutes')), now('UTC')])
            ->whereHas('competition', fn (Builder $competitions) => $competitions->synced())
            ->orderBy('kicks_off_at')
            ->get();
    }

    /**
     * Upcoming fixtures whose team or competition matches the search.
     *
     * @return Collection<int, Fixture>
     */
    public function search(string $term, string $marketKey = 'h2h'): Collection
    {
        $term = Str::of($term)->squish()->limit(60, '')->toString();

        if (mb_strlen($term) < 2) {
            return new Collection;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        return $this->bettable($marketKey)
            ->where(fn (Builder $query) => $query
                ->where('home_team', 'like', $like)
                ->orWhere('away_team', 'like', $like)
                ->orWhereHas('competition', fn (Builder $competitions) => $competitions->where('name', 'like', $like)))
            ->limit(self::LIMIT)
            ->get();
    }

    /**
     * Upcoming fixtures from synced competitions, with the list market loaded and the remaining markets counted.
     *
     * @return Builder<Fixture>
     */
    private function bettable(string $marketKey, ?Competition $competition = null): Builder
    {
        return Fixture::query()
            ->upcoming()
            ->when($competition !== null, fn (Builder $query) => $query->where('competition_id', $competition?->id))
            ->whereHas('competition', fn (Builder $competitions) => $competitions->synced())
            ->whereHas('markets')
            ->with([
                'competition',
                'markets' => fn ($markets) => $markets->where('key', $marketKey)->with('selections'),
            ])
            ->withCount('markets')
            ->orderBy('kicks_off_at');
    }
}

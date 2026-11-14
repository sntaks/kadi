<?php

namespace App\Sportsbook\Catalogue;

use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Support\SportProfile;
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
     * The market switcher for a sport's list: football's 1X2 family, or for a sport without a draw (basketball, ice
     * hockey) the two-way Winner and the main total (`totals`: each match's best-priced line), named per sport
     * ("Total points", "Total goals").
     *
     * @return array<string, string>
     */
    public static function listMarkets(?string $sport): array
    {
        return self::isTwoWay($sport)
            ? ['h2h' => 'Winner', 'totals' => SportProfile::find((string) $sport)->totalsLabel ?? 'Total goals']
            : self::LIST_MARKETS;
    }

    /** Whether a sport's list shows a two-way Winner (no draw). */
    public static function isTwoWay(?string $sport): bool
    {
        return $sport !== null && ! (SportProfile::find($sport)->hasDraw ?? true);
    }

    /**
     * The market a list row shows. `totals` is the match's main line: the one most bookmakers price.
     */
    public static function listMarket(Fixture $fixture, string $marketKey): ?Market
    {
        return $marketKey === 'totals'
            ? $fixture->markets->where('type', MarketType::Totals)->sortByDesc('bookmaker_count')->first()
            : $fixture->markets->firstWhere('key', $marketKey);
    }

    /**
     * Tabs on the home page, with their labels.
     *
     * @var array<string, string>
     */
    public const TABS = [
        'highlights' => 'Highlights',
        'today' => 'Today',
        'tomorrow' => 'Tomorrow',
        'upcoming' => 'This week',
    ];

    private const LIMIT = 60;

    /**
     * @param  int|null  $limit  How many to load (the first page of a list); the list's own cap by default.
     * @return Collection<int, Fixture>
     */
    public function forTab(string $tab, string $marketKey, ?Competition $competition = null, ?int $limit = null, ?string $sport = null): Collection
    {
        return $this->tabQuery($tab, $marketKey, $competition, $sport)->limit($limit ?? self::LIMIT)->get();
    }

    /**
     * How many matches the tab holds, uncapped: the number players see ("All 111 matches") is the real one.
     */
    public function count(string $tab, ?Competition $competition = null, ?string $sport = null): int
    {
        return $this->tabQuery($tab, 'h2h', $competition, $sport)->reorder()->toBase()->getCountForPagination();
    }

    /**
     * @return Builder<Fixture>
     */
    private function tabQuery(string $tab, string $marketKey, ?Competition $competition, ?string $sport = null): Builder
    {
        $localNow = CarbonImmutable::now(config('sportsbook.timezone'));

        $query = $this->bettable($marketKey, $competition, $sport);

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

        return $query;
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
    private function bettable(string $marketKey, ?Competition $competition = null, ?string $sport = null): Builder
    {
        return Fixture::query()
            ->upcoming()
            ->when($competition !== null, fn (Builder $query) => $query->where('competition_id', $competition?->id))
            ->whereHas('competition', fn (Builder $competitions) => $competitions->synced()
                ->when($sport !== null, fn (Builder $query) => SportProfile::scopeCompetitions($query, (string) $sport)))
            ->whereHas('markets')
            ->with([
                'competition',
                'markets' => fn ($markets) => ($marketKey === 'totals' ? $markets->where('type', MarketType::Totals) : $markets->where('key', $marketKey))
                    ->with('selections'),
            ])
            ->withCount('markets')
            ->orderBy('kicks_off_at');
    }
}

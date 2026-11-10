<?php

namespace App\Sportsbook\Catalogue;

use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Support\SportProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * What the shell and the landing show about sports (plan §2): the sports and competitions with matches open for betting and
 * their real counts, the jackpot taking entries, and today's big match. Counts are cached for a minute, because the
 * side menu shows them on every page; nothing is estimated.
 */
class SportsLobby
{
    private const CACHE_SECONDS = 60;

    /**
     * Competitions with matches open for betting, in their curated order, optionally of one sport (by slug).
     *
     * @return list<array{name: string, slug: string, count: int, sport: string, popular: bool}>
     */
    public function competitions(?string $sport = null): array
    {
        $all = Cache::remember('sports.lobby.competitions', self::CACHE_SECONDS, fn (): array => Competition::query()
            ->synced()
            ->with('sport:id,slug')
            ->withCount(['fixtures as open_count' => fn ($fixtures) => $fixtures->upcoming()->whereHas('markets')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Competition $competition): bool => (int) $competition->getAttribute('open_count') > 0)
            ->map(fn (Competition $competition): array => [
                'name' => $competition->name,
                'slug' => $competition->slug,
                'count' => (int) $competition->getAttribute('open_count'),
                'sport' => SportProfile::forSport($competition->sport)->slug,
                'popular' => in_array($competition->odds_api_key, SportProfile::forSport($competition->sport)->popular, true),
            ])
            ->values()
            ->all());

        return $sport === null ? $all : array_values(array_filter($all, fn (array $competition): bool => $competition['sport'] === $sport));
    }

    /**
     * The sports players can bet on, in menu order, with their real counts of matches open. A configured sport with
     * nothing open is left out, so the menu never lists an empty sport.
     *
     * @return list<array{slug: string, name: string, icon: string, count: int}>
     */
    public function sports(): array
    {
        $counts = [];

        foreach ($this->competitions() as $competition) {
            $counts[$competition['sport']] = ($counts[$competition['sport']] ?? 0) + $competition['count'];
        }

        $sports = [];

        foreach (SportProfile::all() as $slug => $profile) {
            if (($counts[$slug] ?? 0) > 0) {
                $sports[] = ['slug' => $slug, 'name' => $profile->name, 'icon' => $profile->icon, 'count' => $counts[$slug]];
            }
        }

        return $sports;
    }

    /** A sport's page: football keeps /sports, the others /sports/{sport}. */
    public static function sportUrl(string $sport): string
    {
        return $sport === 'football' ? route('sports') : route('sports.sport', $sport);
    }

    /**
     * The jackpot taking entries, if any, as the menu and the sports home show it. Cached for a minute like the
     * counts; "still taking entries" is checked against its closing time on every read.
     *
     * @return array{id: int, name: string, entry_price: int, top_prize: int, matches: int, closes_at: int}|null
     */
    public function openJackpot(): ?array
    {
        $summary = Cache::remember('sports.lobby.jackpot', self::CACHE_SECONDS, function (): array|false {
            $jackpot = Jackpot::query()->current()->withCount('jackpotFixtures')->first();
            $closesAt = $jackpot?->closesAt();

            return $jackpot === null || $closesAt === null ? false : [
                'id' => $jackpot->id,
                'name' => $jackpot->name,
                'entry_price' => $jackpot->entry_price,
                'top_prize' => (int) collect($jackpot->prize_tiers)->max('pool'),
                'matches' => (int) $jackpot->getAttribute('jackpot_fixtures_count'),
                'closes_at' => $closesAt->getTimestamp(),
            ];
        });

        return is_array($summary) && $summary['closes_at'] > now()->getTimestamp() ? $summary : null;
    }

    /**
     * The top bar search's football entries: competitions with matches open, and the next seven days' matches by
     * name, read lean (no markets) and cached for a minute.
     *
     * @return list<array{name: string, kind: string, url: string, thumb: null}>
     */
    public function searchEntries(): array
    {
        return Cache::remember('sports.lobby.search', self::CACHE_SECONDS, function (): array {
            $entries = array_map(fn (array $competition): array => [
                'name' => $competition['name'],
                'kind' => (SportProfile::find($competition['sport'])->name ?? 'Football').' · '.$competition['count'].' '.Str::plural('match', $competition['count']),
                'url' => route('sports.competition', $competition['slug']),
                'thumb' => null,
            ], $this->competitions());

            $fixtures = Fixture::query()
                ->upcoming()
                ->where('kicks_off_at', '<=', now('UTC')->addDays(7))
                ->whereHas('competition', fn (Builder $competitions) => $competitions->synced())
                ->whereHas('markets')
                ->with('competition:id,name')
                ->orderBy('kicks_off_at')
                ->limit(300)
                ->get(['id', 'competition_id', 'home_team', 'away_team', 'kicks_off_at']);

            foreach ($fixtures as $fixture) {
                $entries[] = [
                    'name' => $fixture->name(),
                    'kind' => $fixture->competition->name.' · '.$fixture->kickoffLabel(),
                    'url' => route('sports.match', $fixture),
                    'thumb' => null,
                ];
            }

            return $entries;
        });
    }

    /**
     * Today's big match: of the matches kicking off today (Nairobi time) with an open 1X2, the one in the
     * highest-placed competition with the most markets, earliest first. A rule, never a popularity claim. With
     * nothing left today, the next such match.
     */
    public function bigMatch(): ?Fixture
    {
        return $this->bigMatchQuery()->where('kicks_off_at', '<=', $this->endOfToday())->first()
            ?? $this->bigMatchQuery()->first();
    }

    /**
     * What the big match card lists beside the match, in one query: the next kick-offs, soonest first. While any of
     * them is today, only today's are shown ("Also kicking off today"); otherwise the next ones ("Coming up"), so
     * the card never ends empty.
     *
     * @return array{today: bool, fixtures: Collection<int, Fixture>}
     */
    public function beside(Fixture $bigMatch, int $limit = 3): array
    {
        $next = $this->withOpenMatchResult(Fixture::query())
            ->whereKeyNot($bigMatch->id)
            ->with(['matchResultMarket.selections'])
            ->orderBy('kicks_off_at')
            ->limit($limit)
            ->get();

        $endOfToday = $this->endOfToday();
        $today = $next->filter(fn (Fixture $fixture) => $fixture->kicks_off_at->lessThanOrEqualTo($endOfToday))->values();

        return $today->isNotEmpty()
            ? ['today' => true, 'fixtures' => $today]
            : ['today' => false, 'fixtures' => $next];
    }

    /**
     * @return Builder<Fixture>
     */
    private function bigMatchQuery(): Builder
    {
        return $this->withOpenMatchResult(Fixture::query())
            ->with(['competition', 'matchResultMarket.selections'])
            ->withCount('markets')
            ->orderBy(Competition::select('sort_order')->whereColumn('sb_competitions.id', 'sb_fixtures.competition_id'))
            ->orderByDesc('markets_count')
            ->orderBy('kicks_off_at');
    }

    /**
     * @param  Builder<Fixture>  $query
     * @return Builder<Fixture>
     */
    private function withOpenMatchResult(Builder $query): Builder
    {
        // The big match is a football card (1X2, kick-off): sports without a draw never take it.
        $twoWay = array_keys(array_filter(SportProfile::all(), fn (SportProfile $profile): bool => ! $profile->hasDraw));

        return $query->upcoming()
            ->whereHas('competition', fn (Builder $competitions) => $competitions->synced()
                ->whereDoesntHave('sport', fn (Builder $sports) => $sports->whereIn('slug', $twoWay)))
            ->whereHas('matchResultMarket', fn (Builder $markets) => $markets->where('status', MarketStatus::Open));
    }

    private function endOfToday(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('sportsbook.timezone'))->endOfDay()->utc();
    }
}

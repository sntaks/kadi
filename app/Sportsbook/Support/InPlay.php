<?php

namespace App\Sportsbook\Support;

use App\Sportsbook\Enums\FixtureStatus;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The in-play rules (plan §3–§4.3) in one place: whether in play is on, which competitions and markets may be live,
 * and when a live match's markets close. Ask here; never test the switch or the allow-list by hand.
 */
final class InPlay
{
    public static function on(): bool
    {
        return (bool) config('platform.sports') && (bool) config('platform.sports_in_play');
    }

    /** @return list<string> */
    public static function competitionKeys(): array
    {
        return array_values((array) config('sportsbook.in_play.competitions'));
    }

    public static function coversCompetition(?Competition $competition): bool
    {
        return $competition !== null && in_array($competition->odds_api_key, self::competitionKeys(), true);
    }

    /**
     * The feed markets priced in play for a sport (main lines only). Empty for a sport without in play.
     *
     * @return list<MarketType>
     */
    public static function marketTypes(string $sport): array
    {
        $keys = array_filter(array_map('trim', explode(',', (string) config("sportsbook.in_play.markets.{$sport}", ''))));

        return array_values(array_filter(array_map(fn (string $key): ?MarketType => MarketType::tryFrom($key), $keys)));
    }

    /** Whether this market may trade in play: a bulk feed market of an in-play type, in an in-play competition. */
    public static function coversMarket(Market $market, Fixture $fixture): bool
    {
        $sport = SportProfile::forSport($fixture->competition?->sport)->slug;

        return $market->source === MarketSource::Feed
            && in_array($market->type, self::marketTypes($sport), true)
            && self::coversCompetition($fixture->competition);
    }

    /** When live betting on this match closes (estimated from kick-off: the feed has no match clock). */
    public static function closesAt(Fixture $fixture): CarbonImmutable
    {
        $sport = SportProfile::forSport($fixture->competition?->sport)->slug;

        return $fixture->kicks_off_at->addMinutes((int) config("sportsbook.in_play.close_after_minutes.{$sport}", 85));
    }

    /**
     * Matches in play in the in-play competitions: kicked off, still inside the result window, not finished by the
     * feed and with no result recorded.
     *
     * @param  Builder<Fixture>  $query
     * @return Builder<Fixture>
     */
    public static function liveFixtures(Builder $query): Builder
    {
        return $query
            ->where('status', FixtureStatus::Scheduled)
            ->where('kicks_off_at', '<=', now('UTC'))
            ->where('kicks_off_at', '>', now('UTC')->subMinutes((int) config('sportsbook.settlement.result_after_minutes')))
            ->whereNull('feed_completed_at')
            ->whereDoesntHave('result')
            ->whereHas('competition', fn (Builder $competitions) => $competitions->synced()->whereIn('odds_api_key', self::competitionKeys()));
    }
}

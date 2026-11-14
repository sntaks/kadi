<?php

namespace App\Sportsbook\OddsApi;

/**
 * The Odds API v4 (https://the-odds-api.com). Bound to the live or replay driver by ODDS_API_DRIVER.
 *
 * @phpstan-type SportData array{key: string, group: string, title: string, description: string, active: bool, has_outrights: bool}
 * @phpstan-type EventData array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string}
 * @phpstan-type OutcomeData array{name: string, price: float|int, point?: float|int, description?: string}
 * @phpstan-type BookmakerData array{key: string, markets: list<array{key: string, outcomes: list<OutcomeData>}>}
 * @phpstan-type EventOddsData array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<BookmakerData>}
 * @phpstan-type ScoreData array{id: string, sport_key: string, commence_time: string, completed: bool, home_team: string, away_team: string, scores: list<array{name: string, score: string}>|null, last_update: string|null}
 */
interface OddsApi
{
    /**
     * In-season sports and competitions. Free.
     *
     * @return list<SportData>
     */
    public function sports(): array;

    /**
     * Upcoming and in-play fixtures for one competition. Free.
     *
     * @return list<EventData>
     */
    public function events(string $sportKey): array;

    /**
     * Bookmaker prices for one competition. Costs one credit per market per region (or per 10 named bookmakers),
     * counted against $purpose's monthly allowance.
     *
     * @param  list<string>  $markets
     * @return list<EventOddsData>
     */
    public function odds(string $sportKey, array $markets, OddsApiPurpose $purpose = OddsApiPurpose::PreMatch): array;

    /**
     * One match's extra markets (alternate lines, team goal lines...), which the bulk odds endpoint cannot return.
     * Costs one credit per market *returned* per region (or per 10 named bookmakers); the budget checks the most it
     * can cost. Null when the API no longer has the match.
     *
     * @param  list<string>  $markets
     * @return EventOddsData|null
     */
    public function eventOdds(string $sportKey, string $eventId, array $markets, OddsApiPurpose $purpose = OddsApiPurpose::PerEvent): ?array;

    /**
     * Scores for one competition: games in play and, with $daysFrom (1 to 3), games completed in that many days.
     * Costs 2 credits with $daysFrom, 1 without, counted against $purpose's monthly allowance. Full-time scores only:
     * there is no half-time score.
     *
     * @return list<ScoreData>
     */
    public function scores(string $sportKey, int $daysFrom, OddsApiPurpose $purpose = OddsApiPurpose::Scores): array;
}

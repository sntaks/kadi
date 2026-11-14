<?php

namespace App\Sportsbook\OddsApi;

/**
 * What a paid Odds API call is for. Each purpose has its own monthly allowance (config sportsbook.odds_api.allowances),
 * so one use (in play, above all) can never spend the credits another (pre-match prices, settlement) needs.
 */
enum OddsApiPurpose: string
{
    /** Bulk odds for upcoming fixtures (`/sports/{sport}/odds`). */
    case PreMatch = 'pre_match';

    /** One match's extra markets (`/events/{id}/odds`). */
    case PerEvent = 'per_event';

    /** Odds and scores for matches in play. */
    case Live = 'live';

    /** Results for settlement (`/scores`). */
    case Scores = 'scores';

    /** Recordings for the replay driver, made by hand. No allowance of its own: it spends the headroom. */
    case Recording = 'recording';
}

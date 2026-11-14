<?php

namespace App\Sportsbook\Enums;

/**
 * Where a market's prices come from: bookmaker consensus from the feed's bulk odds (each book's main line), the
 * same from one match's extra markets (alternate lines, team goal lines), or calculated from the fixture's 1X2.
 * The bulk feed owns its lines; extras never overwrite them, and derived prices never overwrite either feed.
 */
enum MarketSource: string
{
    case Feed = 'feed';
    case EventFeed = 'event_feed';
    case Derived = 'derived';

    public function isFeed(): bool
    {
        return $this !== self::Derived;
    }
}

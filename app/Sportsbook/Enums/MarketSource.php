<?php

namespace App\Sportsbook\Enums;

/**
 * Where a market's prices come from: bookmaker consensus from the feed, or calculated from the fixture's 1X2.
 */
enum MarketSource: string
{
    case Feed = 'feed';
    case Derived = 'derived';
}

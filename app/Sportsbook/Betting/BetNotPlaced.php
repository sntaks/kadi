<?php

namespace App\Sportsbook\Betting;

use RuntimeException;

/**
 * Why a bet was refused, as a reason code the slip (and later the API) can act on, plus a message for the player.
 */
class BetNotPlaced extends RuntimeException
{
    public const NOT_ALLOWED = 'not-allowed';

    public const EMPTY_SLIP = 'empty-slip';

    public const UNAVAILABLE = 'unavailable';

    public const PRICE_CHANGED = 'price-changed';

    public const DUPLICATE_MATCH = 'duplicate-match';

    public const STAKE = 'stake';

    public const INSUFFICIENT_FUNDS = 'insufficient-funds';

    public const FREE_BET_UNAVAILABLE = 'free-bet-unavailable';

    public const FREE_BET_MIN_ODDS = 'free-bet-min-odds';

    /** The Kadi wallet could not be reached, or refused the bet for a reason the player cannot fix on the slip. */
    public const WALLET = 'wallet';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}

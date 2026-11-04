<?php

namespace App\Sportsbook\Wagers;

use RuntimeException;
use Throwable;

/**
 * No sportsbook player token could be obtained, so no bet was sent. `code` is the Kadi API's reason when it gave
 * one (player_excluded, player_inactive…), or what failed here (not_linked, launch_unavailable, redeem_failed).
 */
class PlayerTokenUnavailable extends RuntimeException
{
    public function __construct(public readonly string $reason, ?Throwable $previous = null)
    {
        parent::__construct("No sportsbook player token ({$reason}).", 0, $previous);
    }
}

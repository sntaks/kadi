<?php

namespace App\Sportsbook\Betting\Jackpot;

use RuntimeException;

/**
 * Why a jackpot entry was refused, as a reason code the page and the API can act on, plus a message for the player.
 */
class JackpotEntryRefused extends RuntimeException
{
    public const NOT_ALLOWED = 'not-allowed';

    public const CLOSED = 'closed';

    public const PICKS = 'picks';

    public const INSUFFICIENT_FUNDS = 'insufficient-funds';

    /** The Kadi wallet could not be reached, or refused the entry for another reason. */
    public const WALLET = 'wallet';

    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}

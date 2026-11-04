<?php

namespace App\Sportsbook\Betting;

use RuntimeException;

/** Money would have to move for a bet or jackpot, but sports betting is not connected to the Kadi API yet. */
class SportsBettingUnavailable extends RuntimeException {}

<?php

namespace App\Sportsbook\Betting;

use App\Sportsbook\Support\Money;

/**
 * Combined odds and payouts, worked out the same way when a bet is quoted, placed and settled.
 *
 * Odds are floored to two decimals and payouts to the cent, both in the player's disfavour by under a cent, as the
 * slip has always shown them. Payouts are capped at sportsbook.limits.max_payout.
 */
class Payout
{
    /** Combined odds beyond this are stored as this; the payout cap is reached long before. */
    public const MAX_ODDS = '999999999.99';

    /**
     * @param  iterable<string>  $prices
     */
    /**
     * The maximum payout in cents: the in-play limit for a bet with any live leg (it must stay within the Kadi API
     * `sportsbook_live` product's max_payout), otherwise the pre-match one.
     */
    public static function cap(bool $inPlay = false): int
    {
        return Money::fromShillings((int) config($inPlay ? 'sportsbook.in_play.max_payout' : 'sportsbook.limits.max_payout'));
    }

    public static function combinedOdds(iterable $prices): string
    {
        $odds = 1.0;

        foreach ($prices as $price) {
            $odds *= (float) $price;
        }

        $odds = floor(round($odds * 100, 6)) / 100;

        return $odds >= (float) self::MAX_ODDS ? self::MAX_ODDS : number_format($odds, 2, '.', '');
    }

    /**
     * Stake times odds, in cents, capped at the maximum payout.
     */
    public static function for(int $stake, string $odds, ?int $cap = null): int
    {
        $cap ??= self::cap();
        $oddsHundredths = (int) round((float) $odds * 100);

        if ($stake <= 0 || $oddsHundredths <= 0) {
            return 0;
        }

        // Checked before multiplying, so huge combined odds can never overflow an integer.
        if ($oddsHundredths > intdiv($cap * 100, $stake)) {
            return $cap;
        }

        return min(intdiv($stake * $oddsHundredths, 100), $cap);
    }
}

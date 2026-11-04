<?php

namespace App\Sportsbook\Promotions;

use App\Sportsbook\Support\Money;

/**
 * The multibet bonus (decision 42): a percentage of a winning multi's profit, set by how many of its legs are priced
 * at least sportsbook.promotions.multibet_bonus.min_price. When a bet settles only the winning legs count, so a void
 * leg can drop it to a lower tier. Never paid on free bets.
 */
class MultibetBonus
{
    /**
     * @param  iterable<string>  $prices  Prices of the legs that count: all legs when quoting, the winning ones at settlement.
     */
    public static function percentFor(iterable $prices): int
    {
        return self::percentForLegs(self::qualifyingLegs($prices));
    }

    /**
     * @param  iterable<string>  $prices
     */
    public static function qualifyingLegs(iterable $prices): int
    {
        $minimum = (int) round((float) config('sportsbook.promotions.multibet_bonus.min_price') * 100);
        $count = 0;

        foreach ($prices as $price) {
            if ((int) round((float) $price * 100) >= $minimum) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The tier for this many qualifying legs: the highest one it reaches, or 0 below the first.
     */
    public static function percentForLegs(int $legs): int
    {
        $percent = 0;

        foreach (self::tiers() as $tierLegs => $tierPercent) {
            if ($legs >= $tierLegs) {
                $percent = $tierPercent;
            }
        }

        return $percent;
    }

    /**
     * The next tier up from this many qualifying legs, for the slip to say how close the player is.
     *
     * @return array{legs: int, percent: int}|null
     */
    public static function nextTier(int $legs): ?array
    {
        foreach (self::tiers() as $tierLegs => $tierPercent) {
            if ($tierLegs > $legs) {
                return ['legs' => $tierLegs, 'percent' => $tierPercent];
            }
        }

        return null;
    }

    /**
     * The bonus in cents on a payout: the percentage of the profit, cut so payout plus bonus stays within the maximum
     * payout.
     */
    public static function amount(int $stake, int $payout, int $percent): int
    {
        if ($percent <= 0 || $payout <= $stake) {
            return 0;
        }

        $cap = Money::fromShillings((int) config('sportsbook.limits.max_payout'));

        return max(0, min(intdiv(($payout - $stake) * $percent, 100), $cap - $payout));
    }

    /**
     * @return array<int, int> Qualifying legs => percent, lowest first.
     */
    private static function tiers(): array
    {
        $tiers = array_map('intval', (array) config('sportsbook.promotions.multibet_bonus.tiers'));
        ksort($tiers);

        return $tiers;
    }
}

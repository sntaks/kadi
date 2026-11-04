<?php

namespace App\Sportsbook\Pricing;

/**
 * Turns a fair probability into the decimal price we offer.
 */
final class Price
{
    /**
     * The price after margin, rounded down to 2 decimals so rounding never favours the player, and kept between the
     * minimum and (when given) maximum price.
     */
    public static function offered(float $fairProbability, float $margin, float $minimum = 1.01, ?float $maximum = null): string
    {
        $price = 1 / ($fairProbability * (1 + $margin));
        $roundedDown = floor(round($price * 100, 6)) / 100;
        $bounded = max($roundedDown, $minimum);

        if ($maximum !== null) {
            $bounded = min($bounded, $maximum);
        }

        return number_format($bounded, 2, '.', '');
    }
}

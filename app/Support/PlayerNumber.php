<?php

namespace App\Support;

/**
 * How a KadiApi `player_no` is shown. KadiApi assigns it (8 digits, the last one a check digit) and
 * returns it as bare digits ("12345671"); players see it grouped as "1234 5678", the way bet shops
 * ask for it. This site never creates or validates one.
 */
class PlayerNumber
{
    public static function format(mixed $playerNo): ?string
    {
        $value = trim((string) $playerNo);

        if ($value === '') {
            return null;
        }

        // Anything that isn't the documented 8 digits is shown as KadiApi sent it.
        return preg_match('/^\d{8}$/', $value) === 1
            ? substr($value, 0, 4).' '.substr($value, 4)
            : $value;
    }
}

<?php

namespace App\Predict;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * How prediction market figures are written everywhere: KES with two decimals, multipliers with two decimals and
 * an `x`, probabilities as whole percents. Also reads the API's loosely typed values.
 */
final class Format
{
    public static function kes(float|int|string|null $amount): string
    {
        return 'KES '.number_format((float) $amount, 2);
    }

    public static function multiplier(float|int|string|null $multiplier): ?string
    {
        return is_numeric($multiplier) ? number_format((float) $multiplier, 2).'x' : null;
    }

    /**
     * A probability from 0 to 1 as a whole percent ("42%").
     */
    public static function percent(float|int|string|null $probability): ?string
    {
        if (! is_numeric($probability)) {
            return null;
        }

        return (int) round(min(1, max(0, (float) $probability)) * 100).'%';
    }

    public static function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function string(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * An ISO 8601 time with its offset (the API mixes +00:00 and +03:00), or null when it is missing or unreadable.
     */
    public static function time(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::instance(Date::parse($value));
        } catch (Throwable) {
            return null;
        }
    }
}

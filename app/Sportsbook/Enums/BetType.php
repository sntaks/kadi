<?php

namespace App\Sportsbook\Enums;

/**
 * One bet per slip (decision 30): one pick is a single, two or more a multi.
 */
enum BetType: string
{
    case Single = 'single';
    case Multi = 'multi';

    public function label(): string
    {
        return match ($this) {
            self::Single => __('Single'),
            self::Multi => __('Multi'),
        };
    }
}

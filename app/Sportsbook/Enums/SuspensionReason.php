<?php

namespace App\Sportsbook\Enums;

enum SuspensionReason: string
{
    case InsufficientBookmakers = 'insufficient-bookmakers';
    case KickedOff = 'kicked-off';
    case StaleOdds = 'stale-odds';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::InsufficientBookmakers => __('Too few bookmakers'),
            self::KickedOff => __('Kicked off'),
            self::StaleOdds => __('Odds out of date'),
            self::Manual => __('Suspended by an admin'),
        };
    }

    /**
     * Whether a fresh odds sync may reopen a market suspended for this reason.
     */
    public function clearsOnReprice(): bool
    {
        return in_array($this, [self::InsufficientBookmakers, self::StaleOdds], true);
    }
}

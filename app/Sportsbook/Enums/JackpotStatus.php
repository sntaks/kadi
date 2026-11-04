<?php

namespace App\Sportsbook\Enums;

enum JackpotStatus: string
{
    /** Being set up by an admin; players cannot see it. */
    case Draft = 'draft';
    /** Published. Entries are taken until the first match kicks off, then it waits for every result. */
    case Open = 'open';
    case Settled = 'settled';
    /** Called off, by an admin or because too many matches were void: every entry was refunded. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Open => __('Open'),
            self::Settled => __('Settled'),
            self::Cancelled => __('Cancelled'),
        };
    }
}

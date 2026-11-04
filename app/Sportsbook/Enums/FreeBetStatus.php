<?php

namespace App\Sportsbook\Enums;

enum FreeBetStatus: string
{
    /** Not used yet. It can still have expired: see FreeBet::isUsable(). */
    case Available = 'available';
    case Used = 'used';
    /** Taken away by an admin, or a replacement taken back when its bet was reopened. */
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Available'),
            self::Used => __('Used'),
            self::Void => __('Void'),
        };
    }
}

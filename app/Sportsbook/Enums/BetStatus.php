<?php

namespace App\Sportsbook\Enums;

enum BetStatus: string
{
    /** Saved, and sent to the Kadi API; the outcome is not known yet ("Checking your bet…"). */
    case Pending = 'pending';
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    /** Every leg was void: the stake went back. */
    case Void = 'void';
    /** Cancelled by an admin: the stake went back. */
    case Cancelled = 'cancelled';
    /** The Kadi API refused it, or it never reached the API: no money moved. The reason is in reject_reason. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Checking'),
            self::Open => __('Open'),
            self::Won => __('Won'),
            self::Lost => __('Lost'),
            self::Void => __('Void'),
            self::Cancelled => __('Cancelled'),
            self::Rejected => __('Not placed'),
        };
    }

    public function isSettled(): bool
    {
        return ! in_array($this, [self::Pending, self::Open, self::Rejected], true);
    }
}

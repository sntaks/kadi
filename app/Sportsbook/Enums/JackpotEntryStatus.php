<?php

namespace App\Sportsbook\Enums;

enum JackpotEntryStatus: string
{
    /** Saved, and sent to the Kadi API; the outcome is not known yet. */
    case Pending = 'pending';
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    /** The jackpot was cancelled: the entry price went back. */
    case Refunded = 'refunded';
    /** The Kadi API refused it, or it never reached the API: no money moved. */
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Checking'),
            self::Open => __('Open'),
            self::Won => __('Won'),
            self::Lost => __('Lost'),
            self::Refunded => __('Refunded'),
            self::Rejected => __('Not placed'),
        };
    }

    public function isSettled(): bool
    {
        return ! in_array($this, [self::Pending, self::Open, self::Rejected], true);
    }
}

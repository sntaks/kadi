<?php

namespace App\Sportsbook\Enums;

enum JackpotEntryStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    /** The jackpot was cancelled: the entry price went back. */
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Won => __('Won'),
            self::Lost => __('Lost'),
            self::Refunded => __('Refunded'),
        };
    }

    public function isSettled(): bool
    {
        return $this !== self::Open;
    }
}

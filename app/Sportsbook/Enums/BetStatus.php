<?php

namespace App\Sportsbook\Enums;

enum BetStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    /** Every leg was void: the stake went back. */
    case Void = 'void';
    /** Cancelled by an admin: the stake went back. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Won => __('Won'),
            self::Lost => __('Lost'),
            self::Void => __('Void'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function isSettled(): bool
    {
        return $this !== self::Open;
    }
}

<?php

namespace App\Sportsbook\Enums;

enum BetLegStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
    /** Counts as odds of 1.00: the match was called off, or the result is a push. */
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open'),
            self::Won => __('Won'),
            self::Lost => __('Lost'),
            self::Void => __('Void'),
        };
    }
}

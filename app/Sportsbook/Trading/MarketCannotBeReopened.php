<?php

namespace App\Sportsbook\Trading;

use DomainException;

class MarketCannotBeReopened extends DomainException
{
    public static function kickedOff(): self
    {
        return new self('The fixture has kicked off, so this market stays closed.');
    }

    public static function staleOdds(): self
    {
        return new self('The odds are out of date. Wait for the next odds sync before reopening.');
    }

    public static function unpriced(): self
    {
        return new self('The market has no prices yet.');
    }
}

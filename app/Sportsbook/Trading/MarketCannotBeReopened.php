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

    public static function liveStale(): self
    {
        return new self('The live odds are out of date. Wait for the next live tick before reopening.');
    }

    public static function scoreChangeHeld(): self
    {
        return new self('The score just changed. The market reopens by itself on the next live prices.');
    }

    public static function lineDecided(): self
    {
        return new self('The score has already decided this line, so it stays closed.');
    }

    public static function unpriced(): self
    {
        return new self('The market has no prices yet.');
    }
}

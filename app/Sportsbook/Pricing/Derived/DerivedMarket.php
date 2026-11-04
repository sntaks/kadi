<?php

namespace App\Sportsbook\Pricing\Derived;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Pricing\PricedMarket;

/**
 * One market calculated from a fixture's 1X2.
 */
final readonly class DerivedMarket
{
    /**
     * @param  array<string, string>  $selectionNames  Outcome => name shown to players.
     */
    public function __construct(
        public MarketType $type,
        public ?float $line,
        public PricedMarket $priced,
        public array $selectionNames,
    ) {}
}

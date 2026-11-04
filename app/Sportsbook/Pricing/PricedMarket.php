<?php

namespace App\Sportsbook\Pricing;

use App\Sportsbook\Enums\SuspensionReason;

/**
 * The result of pricing one market from bookmaker prices.
 */
final readonly class PricedMarket
{
    /**
     * @param  array<string, array{fair_probability: float, price: string}>  $outcomes  Keyed by outcome; empty when suspended.
     */
    public function __construct(
        public int $bookmakerCount,
        public array $outcomes,
        public ?SuspensionReason $suspensionReason = null,
    ) {}

    public function isPriced(): bool
    {
        return $this->suspensionReason === null;
    }
}

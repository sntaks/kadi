<?php

namespace App\Predict\Data;

use App\Predict\Format;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A quote: figures the API holds for this player, side and stake for about 10 seconds, usable once. Its `price`
 * is not a probability and is deliberately not kept, so it can never be shown.
 */
final readonly class Quote
{
    public function __construct(
        public string $id,
        public int $marketId,
        public string $side,
        public float $stake,
        public float $multiplier,
        public float $potentialPayout,
        public CarbonImmutable $expiresAt,
    ) {}

    /**
     * @param  array<string, mixed>  $quote
     */
    public static function fromArray(array $quote): ?self
    {
        $id = $quote['quote_id'] ?? null;
        $expiresAt = Format::time($quote['expires_at'] ?? null);
        $multiplier = Format::float($quote['multiplier'] ?? null);
        $payout = Format::float($quote['potential_payout'] ?? null);

        if (! (is_string($id) || is_int($id)) || $id === '' || $expiresAt === null || $multiplier === null || $payout === null
            || ! is_numeric($quote['market_id'] ?? null) || ! in_array($quote['side'] ?? null, Market::SIDES, true)
            || ! is_numeric($quote['stake'] ?? null)) {
            return null;
        }

        return new self((string) $id, (int) $quote['market_id'], $quote['side'], (float) $quote['stake'], $multiplier, $payout, $expiresAt);
    }

    /**
     * Whole seconds left at $now (our server's clock), never negative. The page counts down from this number, so
     * the browser's clock is never read.
     */
    public function secondsRemaining(DateTimeInterface $now): int
    {
        return max(0, (int) floor((float) $this->expiresAt->format('U.u') - (float) $now->format('U.u')));
    }

    public function expired(DateTimeInterface $now): bool
    {
        return $this->secondsRemaining($now) === 0;
    }

    public function matches(int $marketId, string $side, float $stake): bool
    {
        return $this->marketId === $marketId && $this->side === $side && abs($this->stake - $stake) < 0.005;
    }
}

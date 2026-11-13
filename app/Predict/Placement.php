<?php

namespace App\Predict;

use App\Predict\Data\PredictBet;
use App\Predict\Data\Quote;

/**
 * How a prediction bet attempt ended, for the slip:
 *   placed   the API took it (or had already taken it under this reference: `replayed`);
 *   unknown  no definite answer: the attempt is kept (PendingAttempt) and "Try again" sends the same reference;
 *   changed  the price moved against the house: `multiplier` is the new one, `quote` a fresh one when the API sent it;
 *   refused  a definite no, with what to show and do (`refusal`);
 *   busy     another placement of this player's is still running.
 */
final readonly class Placement
{
    public const PLACED = 'placed';

    public const UNKNOWN = 'unknown';

    public const CHANGED = 'changed';

    public const REFUSED = 'refused';

    public const BUSY = 'busy';

    private function __construct(
        public string $outcome,
        public ?PredictBet $bet = null,
        public bool $replayed = false,
        public ?Refusal $refusal = null,
        public ?float $multiplier = null,
        public ?Quote $quote = null,
        public ?int $retryAfter = null,
    ) {}

    public static function placed(?PredictBet $bet, bool $replayed): self
    {
        return new self(self::PLACED, bet: $bet, replayed: $replayed);
    }

    public static function unknown(?int $retryAfter): self
    {
        return new self(self::UNKNOWN, retryAfter: $retryAfter);
    }

    public static function changed(?float $multiplier, ?Quote $quote): self
    {
        return new self(self::CHANGED, multiplier: $multiplier, quote: $quote);
    }

    public static function refused(Refusal $refusal): self
    {
        return new self(self::REFUSED, refusal: $refusal);
    }

    public static function busy(): self
    {
        return new self(self::BUSY);
    }

    public function is(string $outcome): bool
    {
        return $this->outcome === $outcome;
    }
}

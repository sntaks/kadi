<?php

namespace App\Predict;

use Carbon\CarbonImmutable;

/**
 * What the slip shows after the API said no (or said nothing): one message and, at most, one thing the player
 * can do next.
 */
final readonly class Refusal
{
    /** Open the deposit sheet. */
    public const DEPOSIT = 'deposit';

    /** Ask for a new quote. */
    public const NEW_QUOTE = 'new_quote';

    /** Offer the market's other side. */
    public const OTHER_SIDE = 'other_side';

    /** Offer a button that bets `amount` instead. */
    public const USE_STAKE = 'use_stake';

    /** Type a smaller stake, or come back later. */
    public const SMALLER_STAKE = 'smaller_stake';

    /** Ask again with the same reference, after `retryAfter` seconds when it is set. */
    public const RETRY = 'retry';

    /** Betting on this market is over for now: disable the slip. */
    public const CLOSED = 'closed';

    public function __construct(
        public string $code,
        public string $message,
        public ?string $action = null,
        public ?float $amount = null,
        public ?int $retryAfter = null,
        public ?CarbonImmutable $closesAt = null,
    ) {}
}

<?php

namespace App\Sportsbook\Wagers;

/**
 * What the Kadi API answered to a wager call. A status of null means no answer at all (timeout, connection
 * refused): the call may or may not have reached the API, so its outcome is unknown, never guessed.
 */
final readonly class WagerResponse
{
    /**
     * @param  array<string, mixed>  $data  The `data` block of a success, or the whole body of a refusal.
     */
    public function __construct(
        public ?int $status,
        public ?string $code = null,
        public array $data = [],
    ) {}

    public static function noAnswer(): self
    {
        return new self(null, 'no_answer');
    }

    public function ok(): bool
    {
        return $this->status === 200 || $this->status === 201;
    }

    /**
     * The outcome is not known, or the refusal can pass: ask again later. A 409 out-of-order version also waits,
     * for the op before it to land.
     */
    public function retryable(): bool
    {
        return $this->status === null
            || $this->status >= 500
            || $this->status === 429
            || ($this->status === 409 && $this->code === 'version_out_of_order');
    }

    /** A clear refusal: the API did not do it, and asking again the same way will not change that. */
    public function refused(): bool
    {
        return ! $this->ok() && ! $this->retryable();
    }

    public function invalidPlayerToken(): bool
    {
        return $this->status === 401 && $this->code === 'invalid_player_token';
    }
}

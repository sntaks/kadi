<?php

namespace App\Predict;

/**
 * What the Kadi API answered to a prediction market call. A status of null means no answer at all (timeout,
 * connection refused): the call may or may not have reached the API, so its outcome is unknown, never guessed.
 */
final readonly class PredictResponse
{
    /**
     * @param  array<string, mixed>  $data  The `data` block of a success, or a refusal's extra fields (never its message).
     * @param  int|null  $retryAfter  Seconds from the `Retry-After` header, when the API sent one.
     */
    public function __construct(
        public ?int $status,
        public ?string $code = null,
        public ?string $message = null,
        public array $data = [],
        public ?int $retryAfter = null,
    ) {}

    public static function noAnswer(): self
    {
        return new self(null, 'no_answer');
    }

    public static function notConfigured(): self
    {
        return new self(503, 'not_configured');
    }

    public function ok(): bool
    {
        return $this->status === 200 || $this->status === 201;
    }

    /**
     * Nobody knows whether the API did it: no answer, a 5xx or a 429. Read by status, never by `code`. A bet in
     * this state is asked again with the same reference; it is never treated as refused.
     */
    public function unknown(): bool
    {
        return $this->status === null || $this->status >= 500 || $this->status === 429;
    }

    /** A clear refusal: the API did not do it, and asking again the same way will not change that. */
    public function refused(): bool
    {
        return ! $this->ok() && ! $this->unknown();
    }

    public function rateLimited(): bool
    {
        return $this->status === 429;
    }

    /** A bet the API had already taken under this reference: no money moved now, and `balance` is null. */
    public function replayed(): bool
    {
        return $this->ok() && ($this->data['replayed'] ?? false) === true;
    }

    public function is(string $code): bool
    {
        return $this->code === $code;
    }
}

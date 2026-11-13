<?php

namespace App\Predict\Data;

use App\Predict\Format;
use Carbon\CarbonImmutable;

/**
 * One of a player's prediction bets. An open pool bet's figures are estimates until the market closes; an open
 * quote bet's are fixed. A settled bet's `payout` is what was paid.
 */
final readonly class PredictBet
{
    public function __construct(
        public int $id,
        public int $marketId,
        public ?string $question,
        public ?string $marketStatus,
        public string $side,
        public float $stake,
        public string $status,
        public ?float $multiplier,
        public ?float $potentialPayout,
        public ?float $payout,
        public bool $isEstimate,
        public ?CarbonImmutable $placedAt,
        public ?CarbonImmutable $settledAt,
    ) {}

    /**
     * @param  array<string, mixed>  $bet
     */
    public static function fromArray(array $bet): ?self
    {
        if (! is_numeric($bet['id'] ?? null) || ! is_numeric($bet['market_id'] ?? null)
            || ! in_array($bet['side'] ?? null, Market::SIDES, true) || ! is_numeric($bet['stake'] ?? null)) {
            return null;
        }

        return new self(
            id: (int) $bet['id'],
            marketId: (int) $bet['market_id'],
            question: Format::string($bet['question'] ?? null),
            marketStatus: Format::string($bet['market_status'] ?? null),
            side: $bet['side'],
            stake: (float) $bet['stake'],
            status: Format::string($bet['status'] ?? null) ?? 'open',
            multiplier: Format::float($bet['multiplier'] ?? null),
            potentialPayout: Format::float($bet['potential_payout'] ?? null),
            payout: Format::float($bet['payout'] ?? null),
            isEstimate: ($bet['is_estimate'] ?? false) === true,
            placedAt: Format::time($bet['placed_at'] ?? null),
            settledAt: Format::time($bet['settled_at'] ?? null),
        );
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /**
     * "Fixed odds" or "Estimated" for an open bet; a settled bet has neither.
     */
    public function oddsLabel(): ?string
    {
        if (! $this->isOpen()) {
            return null;
        }

        return $this->isEstimate ? __('Estimated') : __('Fixed odds');
    }
}

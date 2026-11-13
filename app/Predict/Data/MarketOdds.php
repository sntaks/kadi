<?php

namespace App\Predict\Data;

use App\Predict\Format;
use Carbon\CarbonImmutable;

/**
 * A market's `odds` block. On a market these figures are never binding (`is_estimate` is always true there): only
 * a quote, or a placed bet, fixes them. A side's multiplier is null when the API cannot price it right now.
 */
final readonly class MarketOdds
{
    public function __construct(
        public ?float $yesProbability,
        public ?float $noProbability,
        public ?float $yesMultiplier,
        public ?float $noMultiplier,
        public string $yesLabel,
        public string $noLabel,
        public bool $isReference,
        public ?string $attribution,
        public ?float $volume,
        public ?int $betsCount,
        public ?CarbonImmutable $updatedAt,
        public bool $yesBelowStake = false,
        public bool $noBelowStake = false,
    ) {}

    /**
     * @param  array<string, mixed>  $odds
     */
    public static function fromArray(array $odds): self
    {
        $isReference = ($odds['is_reference'] ?? false) === true;

        return new self(
            yesProbability: Format::float($odds['yes_probability'] ?? null),
            noProbability: Format::float($odds['no_probability'] ?? null),
            yesMultiplier: self::playable($odds['yes_multiplier'] ?? null),
            noMultiplier: self::playable($odds['no_multiplier'] ?? null),
            yesLabel: Format::string($odds['yes_label'] ?? null) ?? 'Yes',
            noLabel: Format::string($odds['no_label'] ?? null) ?? 'No',
            isReference: $isReference,
            attribution: $isReference
                ? (Format::string($odds['attribution'] ?? null) ?? (string) config('predict.attribution_fallback'))
                : null,
            volume: Format::float($odds['volume'] ?? null),
            betsCount: is_numeric($odds['bets_count'] ?? null) ? (int) $odds['bets_count'] : null,
            updatedAt: Format::time($odds['updated_at'] ?? null),
            yesBelowStake: self::belowStake($odds['yes_multiplier'] ?? null),
            noBelowStake: self::belowStake($odds['no_multiplier'] ?? null),
        );
    }

    public function multiplier(string $side): ?float
    {
        return $side === 'yes' ? $this->yesMultiplier : $this->noMultiplier;
    }

    public function probability(string $side): ?float
    {
        return $side === 'yes' ? $this->yesProbability : $this->noProbability;
    }

    /**
     * The API prices this side, but a winning bet would pay back no more than the stake (a pool side holding most of
     * the money). Such a side is not on offer, and the page says why rather than showing a blank.
     */
    public function paysBelowStake(string $side): bool
    {
        return $side === 'yes' ? $this->yesBelowStake : $this->noBelowStake;
    }

    public function label(string $side): string
    {
        return $side === 'yes' ? $this->yesLabel : $this->noLabel;
    }

    /**
     * A multiplier that pays more than the stake, or null: a side at 1.00x or less is not on offer.
     */
    private static function belowStake(mixed $multiplier): bool
    {
        $multiplier = Format::float($multiplier);

        return $multiplier !== null && $multiplier > 0 && $multiplier <= 1;
    }

    private static function playable(mixed $multiplier): ?float
    {
        $multiplier = Format::float($multiplier);

        return $multiplier !== null && $multiplier > 1 ? $multiplier : null;
    }
}

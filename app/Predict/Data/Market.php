<?php

namespace App\Predict\Data;

use App\Predict\Format;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * A prediction market as the Kadi API shows it to players. Every field the API might leave out is optional, so a
 * market with gaps still renders; only an id and a question are required.
 */
final readonly class Market
{
    public const SIDES = ['yes', 'no'];

    public function __construct(
        public int $id,
        public string $question,
        public string $source,
        public string $engine,
        public string $status,
        public ?string $description,
        public ?string $resolutionCriteria,
        public ?string $resolutionSource,
        public ?string $category,
        public ?string $imageUrl,
        public ?string $externalUrl,
        public ?string $eventTitle,
        public string $timezone,
        public ?CarbonImmutable $closeAt,
        public ?CarbonImmutable $bettingClosesAt,
        public bool $bettingAvailable,
        public ?CarbonImmutable $resolutionDeadlineAt,
        public ?string $result,
        public ?float $minStake,
        public ?float $maxStake,
        public ?float $maxPayout,
        public ?MarketOdds $odds,
    ) {}

    /**
     * @param  array<string, mixed>  $market
     */
    public static function fromArray(array $market): ?self
    {
        $question = Format::string($market['question'] ?? null);

        if (! is_numeric($market['id'] ?? null) || $question === null) {
            return null;
        }

        $event = is_array($market['event'] ?? null) ? $market['event'] : [];
        $closeAt = Format::time($market['close_at'] ?? null);

        return new self(
            id: (int) $market['id'],
            question: $question,
            source: Format::string($market['source'] ?? null) ?? 'custom',
            engine: Format::string($market['engine'] ?? null) ?? 'pool',
            status: Format::string($market['status'] ?? null) ?? 'closed',
            description: Format::string($market['description'] ?? null),
            resolutionCriteria: Format::string($market['resolution_criteria'] ?? null),
            resolutionSource: Format::string($market['resolution_source'] ?? null),
            category: Format::string($market['category'] ?? null),
            imageUrl: self::httpsUrl($market['image_url'] ?? null),
            externalUrl: self::polymarketUrl($market['external_url'] ?? null),
            eventTitle: Format::string($event['title'] ?? null),
            timezone: Format::string($market['timezone'] ?? null) ?? 'Africa/Nairobi',
            closeAt: $closeAt,
            bettingClosesAt: Format::time($market['betting_closes_at'] ?? null) ?? $closeAt,
            // Missing means "not known to be open": never offer a bet the API did not say it takes.
            bettingAvailable: ($market['betting_available'] ?? false) === true,
            resolutionDeadlineAt: Format::time($market['resolution_deadline_at'] ?? null),
            result: Format::string($market['result'] ?? null),
            minStake: Format::float($market['min_stake'] ?? null),
            maxStake: Format::float($market['max_stake'] ?? null),
            maxPayout: Format::float($market['max_payout'] ?? null),
            odds: is_array($market['odds'] ?? null) ? MarketOdds::fromArray($market['odds']) : null,
        );
    }

    public function isImported(): bool
    {
        return $this->source === 'polymarket';
    }

    /** Reference (Polymarket) and LMSR markets bet through a 10-second quote; pool markets do not. */
    public function usesQuote(): bool
    {
        return $this->engine !== 'pool';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Whether a bet can be offered at $now (our server's clock): the market is open, the API says it takes bets,
     * and the betting cut-off has not passed. The API never announces the cut-off passing, so it is checked here.
     */
    public function takesBets(DateTimeInterface $now): bool
    {
        return $this->isOpen()
            && $this->bettingAvailable
            && ($this->bettingClosesAt === null || $this->bettingClosesAt->greaterThan($now));
    }

    /**
     * Whether this side can be bet at $now: the market takes bets and the side has a price on offer.
     */
    public function sideAvailable(string $side, DateTimeInterface $now): bool
    {
        return $this->takesBets($now) && $this->odds?->multiplier($side) !== null;
    }

    /**
     * Why this market cannot be bet at $now, in a few words, or null while it takes bets.
     */
    public function unavailableReason(DateTimeInterface $now): ?string
    {
        if ($this->takesBets($now)) {
            return null;
        }

        return match ($this->status) {
            'suspended' => 'Betting paused',
            'resolved', 'settled' => in_array($this->result, self::SIDES, true)
                ? 'Result: '.($this->odds?->label($this->result) ?? ucfirst((string) $this->result))
                : 'Result being confirmed',
            'voided' => 'Voided: stakes refunded',
            default => 'Betting closed',
        };
    }

    /**
     * The resolution source as a link, only when it is a polymarket.com page (the only off-site link a market may
     * carry). Anything else an admin typed is shown as plain text.
     */
    public function resolutionSourceUrl(): ?string
    {
        return self::polymarketUrl($this->resolutionSource);
    }

    /** Pool markets pay an estimate fixed when the market closes; the others offer fixed odds through a quote. */
    public function oddsKind(): string
    {
        return $this->usesQuote() ? 'Fixed odds' : 'Estimated';
    }

    private static function httpsUrl(mixed $url): ?string
    {
        $url = Format::string($url);

        return $url !== null && str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * The market's page on polymarket.com, and nothing else: the only off-site link a market may carry.
     */
    private static function polymarketUrl(mixed $url): ?string
    {
        $url = self::httpsUrl($url);
        $host = $url !== null ? strtolower((string) parse_url($url, PHP_URL_HOST)) : null;

        return in_array($host, ['polymarket.com', 'www.polymarket.com'], true) ? $url : null;
    }
}

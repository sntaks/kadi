<?php

namespace App\Predict;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The live part of a market: what can change while a page is open (status, whether it takes bets, the cut-off and
 * the odds). Pure functions, shared by the feed (`MarketFeed`) and the page reads (`Markets`), so both show the same.
 *
 * Two copies exist. The `api` record is what the Kadi API last said, plus our clock reading of when (`fetched_at`).
 * The `shown` record is derived from it at a given moment, because two changes are never announced by the API:
 * - the betting cut-off passing (`betting_available` is computed when the API reads the market);
 * - Polymarket's prices stalling: while `reference_prices_at` is older than the API's own 45 s guard, no
 *   reference (fixed-odds) multiplier is on offer. Pool and LMSR markets, imported or not, never follow
 *   Polymarket's price and are not touched.
 *
 * A reference market's own `odds.updated_at` is not used for that guard: the API keeps capturing an unchanged
 * price without announcing the market again, so our copy of that time goes old while the price is fresh.
 */
final class LiveState
{
    /** The odds fields a page may show. Anything else the API adds stays out of the cache and the broadcast. */
    public const ODDS_FIELDS = [
        'yes_label', 'no_label', 'yes_probability', 'no_probability', 'yes_multiplier', 'no_multiplier',
        'is_estimate', 'is_reference', 'attribution', 'volume', 'bets_count', 'updated_at',
    ];

    /**
     * The live part of a market as the API sent it.
     *
     * @param  array<string, mixed>  $market  one market from any read route
     * @return array{id: int, engine: ?string, status: ?string, betting_available: bool, betting_closes_at: ?string, odds: ?array<string, mixed>, fetched_at: int}|null
     */
    public static function fromApi(array $market, DateTimeInterface $fetchedAt): ?array
    {
        if (! is_numeric($market['id'] ?? null)) {
            return null;
        }

        $odds = is_array($market['odds'] ?? null)
            ? array_intersect_key($market['odds'], array_flip(self::ODDS_FIELDS))
            : null;

        return [
            'id' => (int) $market['id'],
            'engine' => Format::string($market['engine'] ?? null),
            'status' => Format::string($market['status'] ?? null),
            'betting_available' => ($market['betting_available'] ?? false) === true,
            'betting_closes_at' => Format::string($market['betting_closes_at'] ?? null),
            'odds' => $odds,
            'fetched_at' => $fetchedAt->getTimestamp(),
        ];
    }

    /**
     * What a page shows at $now.
     *
     * @param  array<string, mixed>  $api  a `fromApi()` record
     * @param  bool  $referenceStalled  Polymarket's prices are older than the API's guard (`MarketFeed::referenceStalled()`)
     * @return array{id: int, status: ?string, betting_available: bool, betting_closes_at: ?string, odds: ?array<string, mixed>}
     */
    public static function derive(array $api, DateTimeInterface $now, bool $referenceStalled): array
    {
        $odds = $api['odds'] ?? null;
        $closesAt = Format::time($api['betting_closes_at'] ?? null);
        $available = ($api['betting_available'] ?? false) === true
            && ($closesAt === null || CarbonImmutable::instance($now)->lessThan($closesAt));

        if (is_array($odds) && $referenceStalled && ($odds['is_reference'] ?? false) === true) {
            $odds['yes_multiplier'] = null;
            $odds['no_multiplier'] = null;
        }

        return [
            'id' => (int) $api['id'],
            'status' => $api['status'] ?? null,
            'betting_available' => $available,
            'betting_closes_at' => $api['betting_closes_at'] ?? null,
            'odds' => is_array($odds) ? $odds : null,
        ];
    }

    /**
     * A fingerprint of what a player sees. `odds.updated_at` is left out: a price captured again without moving
     * is not a change.
     *
     * @param  array<string, mixed>  $shown  a `derive()` record
     */
    public static function hash(array $shown): string
    {
        $odds = $shown['odds'] ?? null;

        if (is_array($odds)) {
            unset($odds['updated_at']);
            ksort($odds);
        }

        return hash('xxh128', json_encode([
            $shown['status'] ?? null,
            $shown['betting_available'] ?? false,
            $shown['betting_closes_at'] ?? null,
            $odds,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * The market with its live part replaced by $shown, for the page reads.
     *
     * @param  array<string, mixed>  $market
     * @param  array<string, mixed>  $shown
     * @return array<string, mixed>
     */
    public static function apply(array $market, array $shown): array
    {
        $market['status'] = $shown['status'] ?? $market['status'] ?? null;
        $market['betting_available'] = $shown['betting_available'];
        $market['betting_closes_at'] = $shown['betting_closes_at'] ?? $market['betting_closes_at'] ?? null;
        $market['odds'] = $shown['odds'] ?? $market['odds'] ?? null;

        return $market;
    }
}

<?php

namespace App\Sportsbook\Betting;

use App\Sportsbook\Models\Selection;
use App\Sportsbook\Promotions\MultibetBonus;

/**
 * What a bet slip is worth right now: each pick checked against current prices, the combined odds and the possible
 * win for a stake. Built on the server from the player's picks, which are never trusted for prices.
 *
 * The possible win includes the multibet bonus the slip qualifies for if every pick wins. With a free bet it is only
 * the winnings above the stake, since a free bet's stake never comes back, and there is no bonus.
 *
 * @phpstan-type Line array{selection: Selection, seen_price: string, status: 'ok'|'price_changed'|'unavailable', message: string|null}
 */
final readonly class BetSlipQuote
{
    /**
     * @param  list<Line>  $lines
     * @param  list<string>  $stakeErrors
     */
    public function __construct(
        public array $lines,
        public ?float $stake,
        public array $stakeErrors,
        public float $totalOdds,
        public float $possibleWin,
        public bool $isPayoutCapped,
        public bool $isFreeBet = false,
        public int $qualifyingLegs = 0,
        public int $bonusPercent = 0,
        public float $bonus = 0.0,
    ) {}

    /**
     * @param  array<int, mixed>  $picks  [{fixture_id, selection_id, price}] as sent by the browser.
     */
    public static function for(array $picks, string $stake, BettableSelections $bettableSelections, bool $isFreeBet = false): self
    {
        $seenPrices = self::seenPrices($picks);
        $selections = $bettableSelections->find(array_keys($seenPrices));
        $lines = [];
        $fixturesOnSlip = [];

        foreach ($seenPrices as $selectionId => $seenPrice) {
            $selection = $selections->get($selectionId);

            if ($selection === null || isset($fixturesOnSlip[$selection->market->fixture_id])) {
                continue;
            }

            $fixturesOnSlip[$selection->market->fixture_id] = true;
            $unavailable = $bettableSelections->unavailableReason($selection);

            $lines[] = [
                'selection' => $selection,
                'seen_price' => $seenPrice,
                'status' => match (true) {
                    $unavailable !== null => 'unavailable',
                    $selection->price() !== $seenPrice => 'price_changed',
                    default => 'ok',
                },
                'message' => $unavailable,
            ];
        }

        $prices = collect($lines)
            ->reject(fn (array $line): bool => $line['status'] === 'unavailable')
            ->map(fn (array $line): string => (string) $line['selection']->price());

        // The same Payout rules as placement and settlement (integer cents), so the slip never promises a
        // different win from what the bet would pay.
        $combinedOdds = $prices->isEmpty() ? '0.00' : Payout::combinedOdds($prices);
        $totalOdds = (float) $combinedOdds;
        [$stakeAmount, $stakeErrors] = self::validateStake($stake, $lines);
        $stakeCents = $stakeAmount !== null ? (int) round($stakeAmount * 100) : 0;
        $payoutCents = Payout::for($stakeCents, $combinedOdds);
        $uncappedWin = $stakeAmount !== null ? $stakeAmount * $totalOdds : 0.0;
        $maxPayout = (float) config('sportsbook.limits.max_payout');
        $qualifyingLegs = $isFreeBet ? 0 : MultibetBonus::qualifyingLegs($prices);
        $bonusPercent = MultibetBonus::percentForLegs($qualifyingLegs);
        $bonusCents = MultibetBonus::amount($stakeCents, $payoutCents, $bonusPercent);

        return new self(
            $lines,
            $stakeAmount,
            $stakeErrors,
            $totalOdds,
            ($isFreeBet ? max(0, $payoutCents - $stakeCents) : $payoutCents + $bonusCents) / 100,
            $uncappedWin > $maxPayout,
            $isFreeBet,
            $qualifyingLegs,
            $bonusPercent,
            $bonusCents / 100,
        );
    }

    /**
     * The next multibet bonus tier and how many more qualifying picks reach it, for the slip to show.
     *
     * @return array{legs: int, more: int, percent: int}|null
     */
    public function nextBonusTier(): ?array
    {
        $next = $this->isFreeBet ? null : MultibetBonus::nextTier($this->qualifyingLegs);

        return $next === null ? null : [...$next, 'more' => $next['legs'] - $this->qualifyingLegs];
    }

    public function hasPriceChanges(): bool
    {
        return collect($this->lines)->contains(fn (array $line): bool => $line['status'] === 'price_changed');
    }

    public function hasUnavailable(): bool
    {
        return collect($this->lines)->contains(fn (array $line): bool => $line['status'] === 'unavailable');
    }

    public function bettableCount(): int
    {
        return collect($this->lines)->reject(fn (array $line): bool => $line['status'] === 'unavailable')->count();
    }

    /**
     * Current prices of the selections whose price moved, for the player to accept.
     *
     * @return array<int, string>
     */
    public function changedPrices(): array
    {
        return collect($this->lines)
            ->filter(fn (array $line): bool => $line['status'] === 'price_changed')
            ->mapWithKeys(fn (array $line): array => [$line['selection']->id => (string) $line['selection']->price()])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $picks
     * @return array<int, string> Selection id => price the player saw, latest pick last. Malformed picks are dropped.
     */
    private static function seenPrices(array $picks): array
    {
        $seen = [];

        foreach (array_slice($picks, 0, (int) config('sportsbook.limits.max_selections')) as $pick) {
            if (! is_array($pick) || ! is_numeric($pick['selection_id'] ?? null) || ! is_numeric($pick['price'] ?? null)) {
                continue;
            }

            $seen[(int) $pick['selection_id']] = number_format((float) $pick['price'], 2, '.', '');
        }

        return $seen;
    }

    /**
     * @param  list<Line>  $lines
     * @return array{0: float|null, 1: list<string>}
     */
    private static function validateStake(string $stake, array $lines): array
    {
        $stake = trim($stake);

        if ($stake === '') {
            return [null, []];
        }

        if (! is_numeric($stake)) {
            return [null, [__('Enter your stake as a number.')]];
        }

        $amount = (float) $stake;
        $minimum = (int) config('sportsbook.limits.min_stake');
        $maximum = (int) config('sportsbook.limits.max_stake');
        $errors = [];

        if ($amount < $minimum) {
            $errors[] = __('The minimum stake is KES :amount.', ['amount' => number_format($minimum)]);
        }

        if ($amount > $maximum) {
            $errors[] = __('The maximum stake is KES :amount.', ['amount' => number_format($maximum)]);
        }

        return [$amount, $errors];
    }
}

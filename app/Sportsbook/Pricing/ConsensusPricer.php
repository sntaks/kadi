<?php

namespace App\Sportsbook\Pricing;

use App\Sportsbook\Enums\SuspensionReason;

/**
 * Prices a market from several bookmakers' prices without ever offering more than the market as a whole.
 *
 * 1. Each bookmaker's prices are turned into probabilities and scaled to sum to 1, removing that bookmaker's margin.
 * 2. The median probability per outcome is taken, so a single outlying bookmaker cannot move the price.
 * 3. The medians are rescaled to sum to 1, our margin is applied, and each price is rounded down to 2 decimals.
 *
 * Picking the best price across bookmakers instead can produce a book whose prices sum to under 100%, which
 * guarantees a loss to anyone backing every outcome.
 */
final readonly class ConsensusPricer
{
    public function __construct(
        private float $margin,
        private int $minimumBookmakers,
        private float $minimumPrice = 1.01,
    ) {}

    /**
     * @param  array<string, array<string, float>>  $bookmakerPrices  Bookmaker key => [outcome => decimal price].
     * @param  list<string>  $outcomes  Every outcome the market must have, e.g. ['home', 'draw', 'away'].
     */
    public function price(array $bookmakerPrices, array $outcomes): PricedMarket
    {
        $probabilitiesByBookmaker = [];

        foreach ($bookmakerPrices as $prices) {
            $fairProbabilities = $this->removeMargin($prices, $outcomes);

            if ($fairProbabilities !== null) {
                $probabilitiesByBookmaker[] = $fairProbabilities;
            }
        }

        $bookmakerCount = count($probabilitiesByBookmaker);

        if ($bookmakerCount < $this->minimumBookmakers) {
            return new PricedMarket($bookmakerCount, [], SuspensionReason::InsufficientBookmakers);
        }

        $medians = [];

        foreach ($outcomes as $outcome) {
            $medians[$outcome] = $this->median(array_column($probabilitiesByBookmaker, $outcome));
        }

        $total = array_sum($medians);
        $priced = [];

        foreach ($medians as $outcome => $median) {
            $fairProbability = $median / $total;

            $priced[$outcome] = [
                'fair_probability' => round($fairProbability, 6),
                'price' => Price::offered($fairProbability, $this->margin, $this->minimumPrice),
            ];
        }

        return new PricedMarket($bookmakerCount, $priced);
    }

    /**
     * One bookmaker's prices as margin-free probabilities, or null when an outcome is missing or invalid.
     *
     * @param  array<string, float>  $prices
     * @param  list<string>  $outcomes
     * @return array<string, float>|null
     */
    private function removeMargin(array $prices, array $outcomes): ?array
    {
        $implied = [];

        foreach ($outcomes as $outcome) {
            $price = $prices[$outcome] ?? null;

            if ($price === null || $price <= 1.0) {
                return null;
            }

            $implied[$outcome] = 1 / $price;
        }

        $overround = array_sum($implied);

        return array_map(fn (float $probability): float => $probability / $overround, $implied);
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }
}

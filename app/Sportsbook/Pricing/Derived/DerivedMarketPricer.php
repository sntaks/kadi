<?php

namespace App\Sportsbook\Pricing\Derived;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Pricing\Price;
use App\Sportsbook\Pricing\PricedMarket;

/**
 * Prices the markets we offer beyond the feed from a fixture's fair 1X2 probabilities.
 *
 * Double Chance and Draw No Bet are plain arithmetic on the 1X2 and carry the standard margin. Both Teams To Score,
 * Over/Under lines, Correct Score and Half-time/Full-time come from the goals model, so their risk is entirely ours
 * and they carry the higher derived margin.
 */
final readonly class DerivedMarketPricer
{
    /**
     * @var list<float>
     */
    public const TOTALS_LINES = [1.5, 2.5, 3.5];

    public function __construct(
        private float $margin,
        private float $modelMargin,
        private float $firstHalfShare,
        private float $maximumModelPrice,
    ) {}

    /**
     * @param  array{home: float, draw: float, away: float}  $fair  Fair (margin-free) 1X2 probabilities.
     * @param  float  $totalGoals  Expected goals when no Over 2.5 probability is known for the match.
     * @return list<DerivedMarket>
     */
    public function price(array $fair, string $homeTeam, string $awayTeam, int $bookmakerCount, ?float $fairOverTwoAndAHalf = null, float $totalGoals = 2.7): array
    {
        $model = GoalsModel::fit($fair['home'], $fair['draw'], $fair['away'], $fairOverTwoAndAHalf, $totalGoals);

        $markets = [
            $this->market(MarketType::DoubleChance, null, $bookmakerCount, $this->margin, [
                'home_draw' => [$fair['home'] + $fair['draw'], "{$homeTeam} or Draw"],
                'home_away' => [$fair['home'] + $fair['away'], "{$homeTeam} or {$awayTeam}"],
                'draw_away' => [$fair['draw'] + $fair['away'], "Draw or {$awayTeam}"],
            ]),
            $this->market(MarketType::DrawNoBet, null, $bookmakerCount, $this->margin, [
                'home' => [$fair['home'] / ($fair['home'] + $fair['away']), $homeTeam],
                'away' => [$fair['away'] / ($fair['home'] + $fair['away']), $awayTeam],
            ]),
            $this->modelMarket(MarketType::BothTeamsToScore, null, $bookmakerCount, [
                'yes' => [$model->bothTeamsScoreProbability(), 'Yes'],
                'no' => [1 - $model->bothTeamsScoreProbability(), 'No'],
            ]),
        ];

        foreach (self::TOTALS_LINES as $line) {
            $over = $model->overProbability($line);

            $markets[] = $this->modelMarket(MarketType::Totals, $line, $bookmakerCount, [
                'over' => [$over, "Over {$line}"],
                'under' => [1 - $over, "Under {$line}"],
            ]);
        }

        $markets[] = $this->modelMarket(MarketType::CorrectScore, null, $bookmakerCount, $this->correctScores($model));
        $markets[] = $this->modelMarket(MarketType::HalfTimeFullTime, null, $bookmakerCount, $this->halfTimeFullTime($model, $homeTeam, $awayTeam));

        return $markets;
    }

    /**
     * Every score up to 4-4, plus one outcome for anything else.
     *
     * @return array<string, array{0: float, 1: string}>
     */
    private function correctScores(GoalsModel $model): array
    {
        $outcomes = [];
        $listed = 0.0;

        for ($home = 0; $home <= 4; $home++) {
            for ($away = 0; $away <= 4; $away++) {
                $probability = $model->scoreProbability($home, $away);
                $outcomes["{$home}-{$away}"] = [$probability, "{$home}-{$away}"];
                $listed += $probability;
            }
        }

        $outcomes['other'] = [max(1 - $listed, 0.0), 'Any other score'];

        return $outcomes;
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    private function halfTimeFullTime(GoalsModel $model, string $homeTeam, string $awayTeam): array
    {
        $names = ['1' => $homeTeam, 'X' => 'Draw', '2' => $awayTeam];
        $outcomes = [];

        foreach ($model->halfTimeFullTime($this->firstHalfShare) as $key => $probability) {
            [$halfTime, $fullTime] = explode('/', $key);
            $outcomes[$key] = [$probability, "{$names[$halfTime]} / {$names[$fullTime]}"];
        }

        return $outcomes;
    }

    /**
     * A market priced from the goals model: the derived margin, and very long prices capped.
     *
     * @param  array<string, array{0: float, 1: string}>  $outcomes  Outcome => [fair probability, name].
     */
    private function modelMarket(MarketType $type, ?float $line, int $bookmakerCount, array $outcomes): DerivedMarket
    {
        return $this->market($type, $line, $bookmakerCount, $this->modelMargin, $outcomes, $this->maximumModelPrice);
    }

    /**
     * @param  array<string, array{0: float, 1: string}>  $outcomes  Outcome => [fair probability, name].
     */
    private function market(MarketType $type, ?float $line, int $bookmakerCount, float $margin, array $outcomes, ?float $maximumPrice = null): DerivedMarket
    {
        $priced = [];
        $names = [];

        foreach ($outcomes as $outcome => [$probability, $name]) {
            $names[$outcome] = $name;

            if ($probability <= 0.0) {
                continue;
            }

            $priced[$outcome] = [
                'fair_probability' => round($probability, 6),
                'price' => Price::offered($probability, $margin, maximum: $maximumPrice),
            ];
        }

        return new DerivedMarket($type, $line, new PricedMarket($bookmakerCount, $priced), $names);
    }
}

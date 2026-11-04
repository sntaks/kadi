<?php

namespace App\Sportsbook\Pricing\Derived;

/**
 * Goals scored by each team as Poisson counts with a Dixon–Coles adjustment for low scores, fitted to a match.
 *
 * Fitting takes three steps, each matching one market fact:
 * - the total expected goals is set from an Over 2.5 probability (the match's own, or its competition's typical one);
 * - the split of goals between the teams makes P(home) - P(away) match the 1X2;
 * - the Dixon–Coles parameter makes the draw probability match the 1X2.
 *
 * A plain Poisson model under-predicts draws, so fitting goals to the draw instead understated Over 2.5 by about
 * 8 percentage points on recorded matches. The low-score adjustment fixes the draw without distorting the totals.
 */
final class GoalsModel
{
    private const MAX_GOALS = 10;

    private const ITERATIONS = 24;

    /**
     * @var array<int, array<int, float>>|null Probability of each home-away score, normalised to sum to 1.
     */
    private ?array $scoreMatrix = null;

    public function __construct(
        public readonly float $homeRate,
        public readonly float $awayRate,
        public readonly float $rho = 0.0,
    ) {}

    /**
     * @param  float  $totalGoals  Expected goals, used when no Over 2.5 probability is known for the match.
     */
    public static function fit(float $home, float $draw, float $away, ?float $overTwoAndAHalf = null, float $totalGoals = 2.7): self
    {
        // The low-score adjustment only moves probability between 0-0, 1-0, 0-1 and 1-1, all under 2.5 goals, and its
        // changes cancel out, so P(Over 2.5) is exactly the plain Poisson one and the total can be solved directly.
        if ($overTwoAndAHalf !== null) {
            $totalGoals = self::totalGoalsForOverTwoAndAHalf($overTwoAndAHalf);
        }

        return self::fitDrawAndSupremacy($totalGoals, $home, $draw, $away);
    }

    /**
     * The total goals whose Poisson Over 2.5 probability is the one given; used to turn feed totals into goals.
     */
    public static function totalGoalsForOverTwoAndAHalf(float $overTwoAndAHalf): float
    {
        return self::bisect(0.3, 7.0, fn (float $total): bool => 1 - exp(-$total) * (1 + $total + $total ** 2 / 2) < $overTwoAndAHalf);
    }

    private static function fitDrawAndSupremacy(float $totalGoals, float $home, float $draw, float $away): self
    {
        // A lower rho raises 0-0 and 1-1, so the draw probability falls as rho rises.
        $rho = self::bisect(-0.35, 0.35, fn (float $rho): bool => self::fitSupremacy($totalGoals, $home - $away, $rho)->drawProbability() > $draw);

        return self::fitSupremacy($totalGoals, $home - $away, $rho);
    }

    private static function fitSupremacy(float $totalGoals, float $supremacy, float $rho): self
    {
        $homeShare = self::bisect(0.001, 0.999, function (float $share) use ($totalGoals, $supremacy, $rho): bool {
            $model = new self($totalGoals * $share, $totalGoals * (1 - $share), $rho);

            return $model->homeProbability() - $model->awayProbability() < $supremacy;
        });

        return new self($totalGoals * $homeShare, $totalGoals * (1 - $homeShare), $rho);
    }

    /**
     * Bisection on a monotonic condition: true means the answer lies above the midpoint.
     *
     * @param  callable(float): bool  $isBelow
     */
    private static function bisect(float $low, float $high, callable $isBelow): float
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $middle = ($low + $high) / 2;

            if ($isBelow($middle)) {
                $low = $middle;
            } else {
                $high = $middle;
            }
        }

        return ($low + $high) / 2;
    }

    public function homeProbability(): float
    {
        return $this->sumScores(fn (int $home, int $away): bool => $home > $away);
    }

    public function drawProbability(): float
    {
        return $this->sumScores(fn (int $home, int $away): bool => $home === $away);
    }

    public function awayProbability(): float
    {
        return $this->sumScores(fn (int $home, int $away): bool => $home < $away);
    }

    public function overProbability(float $line): float
    {
        return $this->sumScores(fn (int $home, int $away): bool => $home + $away > $line);
    }

    public function bothTeamsScoreProbability(): float
    {
        return $this->sumScores(fn (int $home, int $away): bool => $home > 0 && $away > 0);
    }

    public function scoreProbability(int $home, int $away): float
    {
        return $this->scoreMatrix()[$home][$away] ?? 0.0;
    }

    /**
     * Probabilities of each half-time/full-time result pair, keyed like "1/X" (half-time 1, full-time draw).
     * Each half is its own process with the given share of each team's goals in the first half; the low-score
     * adjustment is applied to the first half only.
     *
     * @return array<string, float>
     */
    public function halfTimeFullTime(float $firstHalfShare): array
    {
        $firstHalf = new self($this->homeRate * $firstHalfShare, $this->awayRate * $firstHalfShare, $this->rho);
        $secondHalf = new self($this->homeRate * (1 - $firstHalfShare), $this->awayRate * (1 - $firstHalfShare));
        $result = fn (int $home, int $away): string => $home > $away ? '1' : ($home === $away ? 'X' : '2');
        $probabilities = array_fill_keys(['1/1', '1/X', '1/2', 'X/1', 'X/X', 'X/2', '2/1', '2/X', '2/2'], 0.0);

        foreach ($firstHalf->scoreMatrix() as $halfHome => $row) {
            foreach ($row as $halfAway => $halfProbability) {
                foreach ($secondHalf->scoreMatrix() as $secondHome => $secondRow) {
                    foreach ($secondRow as $secondAway => $secondProbability) {
                        $key = $result($halfHome, $halfAway).'/'.$result($halfHome + $secondHome, $halfAway + $secondAway);
                        $probabilities[$key] += $halfProbability * $secondProbability;
                    }
                }
            }
        }

        return $probabilities;
    }

    /**
     * @param  callable(int, int): bool  $condition
     */
    private function sumScores(callable $condition): float
    {
        $total = 0.0;

        foreach ($this->scoreMatrix() as $home => $row) {
            foreach ($row as $away => $probability) {
                if ($condition($home, $away)) {
                    $total += $probability;
                }
            }
        }

        return $total;
    }

    /**
     * @return array<int, array<int, float>>
     */
    private function scoreMatrix(): array
    {
        if ($this->scoreMatrix !== null) {
            return $this->scoreMatrix;
        }

        $homeGoals = self::poisson($this->homeRate);
        $awayGoals = self::poisson($this->awayRate);
        $matrix = [];
        $total = 0.0;

        foreach ($homeGoals as $home => $homeProbability) {
            foreach ($awayGoals as $away => $awayProbability) {
                $matrix[$home][$away] = $homeProbability * $awayProbability * $this->lowScoreAdjustment($home, $away);
                $total += $matrix[$home][$away];
            }
        }

        foreach ($matrix as $home => $row) {
            foreach ($row as $away => $probability) {
                $matrix[$home][$away] = $probability / $total;
            }
        }

        return $this->scoreMatrix = $matrix;
    }

    /**
     * The Dixon–Coles factor for scores of 0 and 1 (never negative).
     */
    private function lowScoreAdjustment(int $home, int $away): float
    {
        $factor = match (true) {
            $home === 0 && $away === 0 => 1 - $this->homeRate * $this->awayRate * $this->rho,
            $home === 0 && $away === 1 => 1 + $this->homeRate * $this->rho,
            $home === 1 && $away === 0 => 1 + $this->awayRate * $this->rho,
            $home === 1 && $away === 1 => 1 - $this->rho,
            default => 1.0,
        };

        return max($factor, 0.0);
    }

    /**
     * @return list<float> P(0 goals) ... P(MAX_GOALS goals).
     */
    private static function poisson(float $rate): array
    {
        $probabilities = [exp(-$rate)];

        for ($goals = 1; $goals <= self::MAX_GOALS; $goals++) {
            $probabilities[] = $probabilities[$goals - 1] * $rate / $goals;
        }

        return $probabilities;
    }
}

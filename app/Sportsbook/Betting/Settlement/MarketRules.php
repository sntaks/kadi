<?php

namespace App\Sportsbook\Betting\Settlement;

use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\FixtureResult;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Support\SportProfile;

/**
 * How each market settles from a result. Football markets settle on the full-time (90 minutes plus stoppage) score,
 * except Half-time/Full-time, which also needs the half-time score and waits (null) until an admin enters it.
 * Sports without a draw (basketball) settle on the final score including overtime, which the scores feed reports;
 * their two-way Winner is void if a game is recorded level (it cannot end that way).
 */
class MarketRules
{
    /**
     * The leg's outcome, or null when the result cannot settle it yet.
     */
    public static function settle(BetLeg $leg, FixtureResult $result): ?BetLegStatus
    {
        if ($result->is_void) {
            return BetLegStatus::Void;
        }

        $home = $result->home_score;
        $away = $result->away_score;

        if ($home === null || $away === null) {
            return null;
        }

        $fullTime = self::resultCode($home, $away);

        return match ($leg->market_type) {
            MarketType::MatchResult => $fullTime === 'X' && $leg->outcome !== 'draw' && ! self::hasDraw($leg) ? BetLegStatus::Void : self::wins(match ($leg->outcome) {
                'home' => $fullTime === '1',
                'draw' => $fullTime === 'X',
                'away' => $fullTime === '2',
                default => null,
            }),
            MarketType::DoubleChance => self::wins(match ($leg->outcome) {
                'home_draw' => $fullTime !== '2',
                'home_away' => $fullTime !== 'X',
                'draw_away' => $fullTime !== '1',
                default => null,
            }),
            MarketType::DrawNoBet => $fullTime === 'X' ? BetLegStatus::Void : self::wins(match ($leg->outcome) {
                'home' => $fullTime === '1',
                'away' => $fullTime === '2',
                default => null,
            }),
            MarketType::BothTeamsToScore => self::wins(match ($leg->outcome) {
                'yes' => $home > 0 && $away > 0,
                'no' => $home === 0 || $away === 0,
                default => null,
            }),
            MarketType::Totals => self::totals($leg, $home + $away),
            MarketType::Spreads => self::spreads($leg, $home, $away),
            MarketType::CorrectScore => self::wins($leg->outcome === 'other' ? $home > 4 || $away > 4 : $leg->outcome === "{$home}-{$away}"),
            MarketType::HalfTimeFullTime => $result->hasHalfTimeScore()
                ? self::wins($leg->outcome === self::resultCode((int) $result->half_time_home_score, (int) $result->half_time_away_score).'/'.$fullTime)
                : null,
        };
    }

    /**
     * "1" home win, "X" draw, "2" away win.
     */
    public static function resultCode(int $home, int $away): string
    {
        return match (true) {
            $home > $away => '1',
            $home < $away => '2',
            default => 'X',
        };
    }

    private static function hasDraw(BetLeg $leg): bool
    {
        $leg->loadMissing('fixture.competition.sport');

        return SportProfile::forSport($leg->fixture?->competition?->sport)->hasDraw;
    }

    /**
     * Over/under the line. A total landing exactly on a whole-number line is a push: void.
     */
    private static function totals(BetLeg $leg, int $goals): ?BetLegStatus
    {
        if ($leg->market_line === null) {
            return null;
        }

        $line = (float) $leg->market_line;

        // Quarter lines are never offered; one that slipped through is left for an admin, not settled whole.
        if (Market::isQuarterLine($line)) {
            return null;
        }

        if ((float) $goals === $line) {
            return BetLegStatus::Void;
        }

        return self::wins(match ($leg->outcome) {
            'over' => $goals > $line,
            'under' => $goals < $line,
            default => null,
        });
    }

    /**
     * Handicap: the line (the home side's handicap) is added to the home score. Home wins the bet if it is then
     * ahead, away if behind; level is a push: void. Football uses the full-time score, sports without a draw the
     * final score including overtime, like every other market.
     */
    private static function spreads(BetLeg $leg, int $home, int $away): ?BetLegStatus
    {
        if ($leg->market_line === null || Market::isQuarterLine($leg->market_line)) {
            return null;
        }

        $margin = $home + (float) $leg->market_line - $away;

        if (abs($margin) < 0.001) {
            return BetLegStatus::Void;
        }

        return self::wins(match ($leg->outcome) {
            'home' => $margin > 0,
            'away' => $margin < 0,
            default => null,
        });
    }

    /**
     * Won or lost; null for an outcome this market does not have (left for an admin).
     */
    private static function wins(?bool $won): ?BetLegStatus
    {
        return match ($won) {
            true => BetLegStatus::Won,
            false => BetLegStatus::Lost,
            null => null,
        };
    }
}

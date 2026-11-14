<?php

namespace App\Sportsbook\Enums;

/**
 * Market types. Feed markets use their The Odds API market key; the rest are derived from the 1X2.
 */
enum MarketType: string
{
    case MatchResult = 'h2h';
    case Totals = 'totals';
    case Spreads = 'spreads';
    case DoubleChance = 'double_chance';
    case DrawNoBet = 'draw_no_bet';
    case BothTeamsToScore = 'btts';
    case CorrectScore = 'correct_score';
    case HalfTimeFullTime = 'ht_ft';

    public function label(): string
    {
        return match ($this) {
            self::MatchResult => __('1X2'),
            self::Totals => __('Over/Under'),
            self::Spreads => __('Handicap'),
            self::DoubleChance => __('Double Chance'),
            self::DrawNoBet => __('Draw No Bet'),
            self::BothTeamsToScore => __('Both Teams To Score'),
            self::CorrectScore => __('Correct Score'),
            self::HalfTimeFullTime => __('Half-time / Full-time'),
        };
    }

    /**
     * Whether prices for this market can be bought from The Odds API.
     */
    public function isFeedMarket(): bool
    {
        return in_array($this, [self::MatchResult, self::Totals, self::Spreads], true);
    }

    /**
     * Whether this market is priced per line (Over/Under 2.5, Handicap -1.5): each line is its own market.
     */
    public function hasLines(): bool
    {
        return in_array($this, [self::Totals, self::Spreads], true);
    }
}

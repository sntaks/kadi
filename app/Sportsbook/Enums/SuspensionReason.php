<?php

namespace App\Sportsbook\Enums;

enum SuspensionReason: string
{
    case InsufficientBookmakers = 'insufficient-bookmakers';
    case KickedOff = 'kicked-off';
    case StaleOdds = 'stale-odds';
    case Manual = 'manual';
    // In play (plan §4.3).
    case ScoreChanged = 'score-changed';
    case LiveFeedStale = 'live-feed-stale';
    case LatePhase = 'late-phase';
    case LineDecided = 'line-decided';
    case InPlayOff = 'in-play-off';

    public function label(): string
    {
        return match ($this) {
            self::InsufficientBookmakers => __('Too few bookmakers'),
            self::KickedOff => __('Kicked off'),
            self::StaleOdds => __('Odds out of date'),
            self::Manual => __('Suspended by an admin'),
            self::ScoreChanged => __('Score changed'),
            self::LiveFeedStale => __('Live prices out of date'),
            self::LatePhase => __('Closed for the end of the match'),
            self::LineDecided => __('Already decided'),
            self::InPlayOff => __('In-play betting is off'),
        };
    }

    /**
     * Whether a fresh odds sync may reopen a market suspended for this reason.
     */
    public function clearsOnReprice(): bool
    {
        return in_array($this, [self::InsufficientBookmakers, self::StaleOdds, self::ScoreChanged, self::LiveFeedStale, self::InPlayOff], true);
    }
}

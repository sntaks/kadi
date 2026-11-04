<?php

namespace App\Sportsbook\Enums;

/**
 * Which price changes a player accepts between adding picks and placing the bet. The bet is always placed at the
 * current prices; the policy only decides whether a change stops it.
 */
enum OddsChangePolicy: string
{
    case Any = 'any';
    case Higher = 'higher';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Any => __('Accept any odds changes'),
            self::Higher => __('Accept higher odds only'),
            self::None => __('Do not accept odds changes'),
        };
    }

    /**
     * Whether a pick seen at one price may be placed at another.
     */
    public function accepts(string $seenPrice, string $currentPrice): bool
    {
        return match ($this) {
            self::Any => true,
            self::Higher => (float) $currentPrice >= (float) $seenPrice,
            self::None => $currentPrice === $seenPrice,
        };
    }
}

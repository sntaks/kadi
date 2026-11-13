<?php

namespace App\Support;

use App\Models\User;

/**
 * Whether the prediction market pages are on (PLATFORM_PREDICTIONS) and who may place bets there. Everything that
 * shows or takes prediction bets asks here; never re-check the switches by hand.
 */
final class PredictPages
{
    public static function on(): bool
    {
        return (bool) config('platform.predictions');
    }

    /**
     * Betting is open to every player (PLATFORM_PREDICTIONS_BETTING).
     */
    public static function bettingOn(): bool
    {
        return self::on() && (bool) config('platform.predictions_betting');
    }

    /**
     * Whether this player may place bets: betting is open, or it is closed and they are on the testers list
     * (PREDICT_BETTING_TESTERS), which lets staff place real bets before it opens.
     */
    public static function bettingFor(?User $user): bool
    {
        if (! self::on()) {
            return false;
        }

        if (self::bettingOn()) {
            return true;
        }

        return $user instanceof User
            && in_array((int) $user->getKey(), (array) config('predict.betting_testers', []), true);
    }
}

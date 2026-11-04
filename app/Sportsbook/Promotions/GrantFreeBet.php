<?php

namespace App\Sportsbook\Promotions;

use App\Sportsbook\Enums\FreeBetSource;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\FreeBet;

/**
 * Free bets are local vouchers (plan §5.3): the stake of a free bet never comes back as cash, so a free bet whose bet
 * ended void or was cancelled is replaced by a new one. Staff and referral grants arrive with the back office
 * (Phase 10) and promotions (Phase 11).
 */
class GrantFreeBet
{
    /**
     * A new free bet of the same amount and minimum odds for a free bet whose bet ended void or was cancelled.
     */
    public function replacing(Bet $bet): FreeBet
    {
        $original = $bet->freeBet()->firstOrFail();

        return FreeBet::create([
            'user_id' => $bet->user_id,
            'amount' => $bet->stake,
            'min_odds' => $original->min_odds,
            'expires_at' => now('UTC')->addDays((int) config('sportsbook.promotions.free_bets.replacement_days')),
            'status' => FreeBetStatus::Available,
            'source' => FreeBetSource::Replacement,
            'note' => __('Replaces the free bet on bet :reference', ['reference' => $bet->reference]),
            'replaces_bet_id' => $bet->id,
        ]);
    }
}

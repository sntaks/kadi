<?php

namespace App\Sportsbook\Betting;

use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\Jackpot;

/**
 * Where settling a bet or a jackpot moves money (plan §5.3). The merged sportsbook never keeps a wallet of its
 * own: from Phase 8 this is the Kadi API (wager settle, reverse), with the sb_wager_ops outbox. Until then it is
 * NullSportsWallet, which refuses to move any money; no bets or jackpot entries can be placed yet, so recording
 * results never needs it.
 */
interface SportsWallet
{
    /** Settle a bet whose legs are all decided (won, lost or void) and pay what it owes. */
    public function settleBet(Bet $bet): Bet;

    /** Take back what an earlier settlement paid, so the bet can be settled again on a corrected result. */
    public function reopenBet(Bet $bet): void;

    /** Settle a jackpot once every match is decided, paying each prize tier (or cancelling past the void limit). */
    public function settleJackpot(Jackpot $jackpot): void;

    /** Take back a settled jackpot's prizes, so it can be settled again on a corrected result. */
    public function reopenJackpot(Jackpot $jackpot): void;
}

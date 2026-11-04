<?php

namespace App\Sportsbook\Betting;

use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\Jackpot;

/**
 * The SportsWallet until bets are paid through the Kadi API (Phase 8). It never moves money: a bet cannot be
 * placed yet, so asking to settle one is a bug, and a jackpot with entries cannot be paid. A jackpot nobody
 * entered owes nothing, so recording its results is allowed and leaves it waiting to be settled.
 */
class NullSportsWallet implements SportsWallet
{
    public function settleBet(Bet $bet): Bet
    {
        throw new SportsBettingUnavailable("Bet {$bet->reference} cannot be settled: sports betting is not connected to the Kadi API yet.");
    }

    public function reopenBet(Bet $bet): void
    {
        throw new SportsBettingUnavailable("Bet {$bet->reference} cannot be reopened: sports betting is not connected to the Kadi API yet.");
    }

    public function settleJackpot(Jackpot $jackpot): void
    {
        $this->refuseIfEntered($jackpot, 'settled');
    }

    public function reopenJackpot(Jackpot $jackpot): void
    {
        $this->refuseIfEntered($jackpot, 'reopened');
    }

    private function refuseIfEntered(Jackpot $jackpot, string $action): void
    {
        if ($jackpot->entries()->exists()) {
            throw new SportsBettingUnavailable("Jackpot {$jackpot->id} cannot be {$action}: sports betting is not connected to the Kadi API yet.");
        }
    }
}

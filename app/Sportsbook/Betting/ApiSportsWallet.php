<?php

namespace App\Sportsbook\Betting;

use App\Sportsbook\Betting\Jackpot\ReopenJackpot;
use App\Sportsbook\Betting\Jackpot\SettleJackpot;
use App\Sportsbook\Betting\Settlement\ReopenBet;
use App\Sportsbook\Betting\Settlement\SettleBet;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\Jackpot;

/**
 * The SportsWallet of the merged sportsbook: settlements move money in the one Kadi wallet, through the sb_wager_ops
 * outbox (plan §5.3). Each call decides locally and writes its ops in the same database transaction; the outbox sends
 * them to the API in order. Nothing here talks to the API directly.
 */
class ApiSportsWallet implements SportsWallet
{
    public function __construct(
        private SettleBet $settleBet,
        private ReopenBet $reopenBet,
        private SettleJackpot $settleJackpot,
        private ReopenJackpot $reopenJackpot,
    ) {}

    public function settleBet(Bet $bet): Bet
    {
        return $this->settleBet->handle($bet);
    }

    public function reopenBet(Bet $bet): void
    {
        $this->reopenBet->handle($bet);
    }

    public function settleJackpot(Jackpot $jackpot): void
    {
        $this->settleJackpot->handle($jackpot);
    }

    public function reopenJackpot(Jackpot $jackpot): void
    {
        $this->reopenJackpot->handle($jackpot);
    }
}

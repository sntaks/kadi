<?php

namespace App\Sportsbook\Betting\Settlement;

use App\Sportsbook\Betting\Payout;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Promotions\GrantFreeBet;
use App\Sportsbook\Promotions\MultibetBonus;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Support\Facades\DB;

/**
 * Settles one open bet from its legs, once:
 * - any leg lost: the bet is lost at once, even with other legs still to play;
 * - otherwise it waits until every leg is decided, then pays the stake times the odds of the winning legs (a void
 *   leg counts as 1.00), capped at the maximum payout;
 * - every leg void: the bet is void, and the API gives the stake back.
 *
 * A free bet's stake was never the player's money: a win pays only the winnings above it, and an all-void bet gets a
 * replacement free bet instead of a refund. A cash multi also gets the multibet bonus on its profit, by its winning
 * legs priced high enough, never above the tier it qualified for when placed.
 *
 * The money moves at the Kadi API: the decision is saved with a settle op of the bet's next wager version in the same
 * transaction (plan §5.3), and the API withholds any winnings tax. Pending and rejected bets are never settled.
 */
class SettleBet
{
    public function __construct(private WagerOps $ops, private GrantFreeBet $grantFreeBet) {}

    public function handle(Bet $bet): Bet
    {
        return DB::transaction(function () use ($bet): Bet {
            $bet = Bet::query()->whereKey($bet->id)->lockForUpdate()->firstOrFail();

            if ($bet->status !== BetStatus::Open) {
                return $bet;
            }

            $legs = $bet->legs()->get();

            if ($legs->contains(fn (BetLeg $leg): bool => $leg->status === BetLegStatus::Lost)) {
                $bet->update(['status' => BetStatus::Lost, 'payout' => 0, 'bonus' => 0, 'settled_at' => now('UTC')]);
                $this->ops->settle($bet, $bet->wagerVersion(), 'lost', 0);
                $this->ops->sendAfterCommit();

                return $bet;
            }

            if ($legs->contains(fn (BetLeg $leg): bool => $leg->status === BetLegStatus::Open)) {
                return $bet;
            }

            $wonPrices = $legs->filter(fn (BetLeg $leg): bool => $leg->status === BetLegStatus::Won)->map(fn (BetLeg $leg): string => $leg->price);

            if ($wonPrices->isEmpty()) {
                if ($bet->isFreeBet()) {
                    $this->grantFreeBet->replacing($bet);
                }

                $bet->update(['status' => BetStatus::Void, 'payout' => $bet->isFreeBet() ? 0 : $bet->stake, 'bonus' => 0, 'settled_at' => now('UTC')]);
                $this->ops->settle($bet, $bet->wagerVersion(), 'void', 0, reason: 'Every selection was void');
                $this->ops->sendAfterCommit();

                return $bet;
            }

            $payout = Payout::for($bet->stake, Payout::combinedOdds($wonPrices));
            $winnings = $bet->isFreeBet() ? max(0, $payout - $bet->stake) : $payout;
            $bonus = $bet->isFreeBet() ? 0 : MultibetBonus::amount($bet->stake, $payout, min($bet->bonus_percent, MultibetBonus::percentFor($wonPrices)));

            $bet->update(['status' => BetStatus::Won, 'payout' => $winnings + $bonus, 'bonus' => $bonus, 'tax' => null, 'settled_at' => now('UTC')]);
            $this->ops->settle($bet, $bet->wagerVersion(), 'won', $winnings, $bonus);
            $this->ops->sendAfterCommit();

            return $bet;
        });
    }
}

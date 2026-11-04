<?php

namespace App\Sportsbook\Betting\Settlement;

use App\Models\User;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Promotions\GrantFreeBet;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Staff cancel an open bet (for example one placed in error): a void settle op gives the stake back through the API,
 * and the bet is closed as cancelled. Settled bets are corrected through results or leg voids instead, never
 * cancelled, and a bet still being checked must be resolved first. A free bet gets a replacement free bet, not cash.
 */
class CancelBet
{
    public function __construct(private WagerOps $ops, private GrantFreeBet $grantFreeBet) {}

    /**
     * @throws ValidationException
     */
    public function handle(Bet $bet, User $admin, string $reason): Bet
    {
        $bet = DB::transaction(function () use ($bet, $reason): Bet {
            $bet = Bet::query()->whereKey($bet->id)->lockForUpdate()->firstOrFail();

            if ($bet->status !== BetStatus::Open) {
                throw ValidationException::withMessages(['bet' => __('Only open bets can be cancelled.')]);
            }

            if ($bet->isFreeBet()) {
                $this->grantFreeBet->replacing($bet);
            }

            $bet->legs()->where('status', BetLegStatus::Open)->update(['status' => BetLegStatus::Void, 'void_reason' => mb_substr($reason, 0, 255), 'settled_at' => now('UTC')]);
            $bet->update(['status' => BetStatus::Cancelled, 'payout' => $bet->isFreeBet() ? 0 : $bet->stake, 'bonus' => 0, 'settled_at' => now('UTC')]);
            $this->ops->settle($bet, $bet->wagerVersion(), 'void', 0, reason: 'Cancelled: '.$reason);
            $this->ops->sendAfterCommit();

            return $bet;
        });

        activity('settlement')
            ->causedBy($admin)
            ->performedOn($bet)
            ->event('bet-cancelled')
            ->withProperties(['reference' => $bet->reference, 'reason' => $reason])
            ->log('Bet cancelled');

        return $bet;
    }
}

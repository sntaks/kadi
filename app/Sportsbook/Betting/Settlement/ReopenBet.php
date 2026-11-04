<?php

namespace App\Sportsbook\Betting\Settlement;

use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\FreeBetStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a settlement so the bet can settle again after a correction: a reverse op of its current wager version
 * takes back what that settlement paid (the API allows the balance to go negative), and the bet is open again under
 * the next version. Open, pending, rejected and cancelled bets are left as they are.
 *
 * A void free bet paid nothing, but was given a replacement free bet: that is taken back if still unused. If the
 * player already bet with it, it stays theirs, and the activity log records it.
 */
class ReopenBet
{
    public function __construct(private WagerOps $ops) {}

    public function handle(Bet $bet, string $reason = 'Result corrected'): Bet
    {
        return DB::transaction(function () use ($bet, $reason): Bet {
            $bet = Bet::query()->whereKey($bet->id)->lockForUpdate()->firstOrFail();

            if (! in_array($bet->status, [BetStatus::Won, BetStatus::Lost, BetStatus::Void], true)) {
                return $bet;
            }

            $this->ops->reverse($bet, $bet->wagerVersion(), $reason);
            $this->ops->sendAfterCommit();

            if ($bet->isFreeBet() && $bet->status === BetStatus::Void) {
                $this->takeBackReplacement($bet);
            }

            $bet->update([
                'status' => BetStatus::Open,
                'payout' => null,
                'bonus' => null,
                'tax' => null,
                'settled_at' => null,
                'settlement_version' => $bet->settlement_version + 1,
            ]);

            return $bet;
        });
    }

    private function takeBackReplacement(Bet $bet): void
    {
        $replacement = FreeBet::query()
            ->where('replaces_bet_id', $bet->id)
            ->where('status', '!=', FreeBetStatus::Void)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($replacement?->status === FreeBetStatus::Available) {
            $replacement->update(['status' => FreeBetStatus::Void, 'void_reason' => __('Its bet was reopened by a corrected result.'), 'voided_at' => now('UTC')]);
        } elseif ($replacement !== null) {
            activity('settlement')
                ->performedOn($bet)
                ->event('replacement-free-bet-kept')
                ->withProperties(['reference' => $bet->reference, 'free_bet_id' => $replacement->id])
                ->log('Replacement free bet already used, so not taken back');
        }
    }
}

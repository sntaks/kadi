<?php

namespace App\Sportsbook\Betting\Settlement;

use App\Models\User;
use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Support\StaffActor;
use Illuminate\Support\Facades\DB;

/**
 * Voids one leg on its own: its match was postponed, or (by an admin) it was priced in error. The leg then counts
 * as 1.00 and the bet settles again, reopening it first if it had already settled. The leg stays void even if the
 * match result is later corrected.
 */
class VoidLeg
{
    public function __construct(private SportsWallet $wallet) {}

    public function handle(BetLeg $leg, string $reason, User|StaffActor|null $admin = null): BetLeg
    {
        return DB::transaction(function () use ($leg, $reason, $admin): BetLeg {
            $leg = BetLeg::query()->whereKey($leg->id)->lockForUpdate()->firstOrFail();

            if ($leg->status === BetLegStatus::Void && $leg->void_reason !== null) {
                return $leg;
            }

            $this->wallet->reopenBet($leg->bet);
            $leg->update(['status' => BetLegStatus::Void, 'void_reason' => mb_substr($reason, 0, 255), 'settled_at' => now('UTC')]);
            $this->wallet->settleBet($leg->bet);

            if ($admin !== null) {
                activity('settlement')
                    ->causedBy(StaffActor::causer($admin))
                    ->performedOn($leg->bet)
                    ->event('leg-voided')
                    ->withProperties(StaffActor::with($admin, ['leg' => "{$leg->fixture_name}: {$leg->market_name} {$leg->selection_name} @ {$leg->price}", 'reason' => $reason]))
                    ->log('Bet leg voided');
            }

            return $leg;
        });
    }
}

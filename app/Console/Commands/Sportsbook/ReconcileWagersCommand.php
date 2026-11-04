<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Enums\JackpotStatus;
use App\Sportsbook\Jobs\ReconcileWager;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\Jackpot;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('sportsbook:reconcile-wagers')]
#[Description('Look up sports bets and jackpot entries whose placement got no answer, and settle any open bet or jackpot whose results are all in')]
class ReconcileWagersCommand extends Command
{
    public function handle(SportsWallet $wallet): int
    {
        $before = now('UTC')->subSeconds(WagerPlacement::RECONCILE_DELAY_SECONDS);
        $dispatched = 0;

        foreach (['bet' => Bet::query()->where('status', BetStatus::Pending), 'jackpot_entry' => JackpotEntry::query()->where('status', JackpotEntryStatus::Pending)] as $type => $query) {
            foreach ($query->where('placed_at', '<', $before)->pluck('id') as $id) {
                if (! WagerPlacement::onHold($type, (int) $id)) {
                    ReconcileWager::dispatch($type, (int) $id);
                    $dispatched++;
                }
            }
        }

        $settled = $this->settleMissed($wallet);

        if ($dispatched + $settled > 0) {
            $this->info("Looking up {$dispatched} pending placements; settled {$settled} bets or jackpots that were waiting.");
        }

        return self::SUCCESS;
    }

    /**
     * A bet that opened after its results were in is settled right away; this catches one whose settling was cut
     * short (a worker stopped in between), and a jackpot that waited on an entry being checked.
     */
    private function settleMissed(SportsWallet $wallet): int
    {
        $settled = 0;

        $bets = Bet::query()
            ->where('status', BetStatus::Open)
            ->where(fn (Builder $query) => $query
                ->whereHas('legs', fn (Builder $legs) => $legs->where('status', BetLegStatus::Lost))
                ->orWhereDoesntHave('legs', fn (Builder $legs) => $legs->where('status', BetLegStatus::Open)))
            ->limit(500)
            ->get();

        foreach ($bets as $bet) {
            try {
                $settled += $wallet->settleBet($bet)->status->isSettled() ? 1 : 0;
            } catch (Throwable $e) {
                Log::warning('Sportsbook: settling a waiting bet failed', ['bet' => $bet->id, 'error' => class_basename($e)]);
            }
        }

        $jackpots = Jackpot::query()
            ->where('status', JackpotStatus::Open)
            ->whereDoesntHave('jackpotFixtures', fn (Builder $matches) => $matches->whereNull('outcome')->where('is_void', false))
            ->whereDoesntHave('entries', fn (Builder $entries) => $entries->where('status', JackpotEntryStatus::Pending))
            ->get();

        foreach ($jackpots as $jackpot) {
            try {
                $wallet->settleJackpot($jackpot);
                $settled += $jackpot->fresh()?->status !== JackpotStatus::Open ? 1 : 0;
            } catch (Throwable $e) {
                Log::warning('Sportsbook: settling a waiting jackpot failed', ['jackpot' => $jackpot->id, 'error' => class_basename($e)]);
            }
        }

        return $settled;
    }
}

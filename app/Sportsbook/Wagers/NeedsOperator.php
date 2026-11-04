<?php

namespace App\Sportsbook\Wagers;

use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\WagerOp;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * What needs a person in the wager outbox: failed or overdue ops, and placements whose outcome is still unknown after
 * `sportsbook.wagers.pending_alert_minutes`. One rule for `sportsbook:wager-ops` and the GMS reconciliation.
 */
class NeedsOperator
{
    private const LIMIT = 200;

    /** @return Collection<int, WagerOp> */
    public function ops(): Collection
    {
        return WagerOp::query()->needsOperator()->orderBy('id')->limit(self::LIMIT)->get();
    }

    /** @return Collection<int, Bet> */
    public function pendingBets(): Collection
    {
        return Bet::query()->where('status', BetStatus::Pending)->where('placed_at', '<', $this->alertBefore())
            ->orderBy('placed_at')->limit(self::LIMIT)->get(['id', 'reference', 'wager_reference', 'placed_at']);
    }

    /** @return Collection<int, JackpotEntry> */
    public function pendingJackpotEntries(): Collection
    {
        return JackpotEntry::query()->where('status', JackpotEntryStatus::Pending)->where('placed_at', '<', $this->alertBefore())
            ->orderBy('placed_at')->limit(self::LIMIT)->get(['id', 'reference', 'wager_reference', 'placed_at']);
    }

    private function alertBefore(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->subMinutes((int) config('sportsbook.wagers.pending_alert_minutes'));
    }
}

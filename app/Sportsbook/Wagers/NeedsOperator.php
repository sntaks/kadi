<?php

namespace App\Sportsbook\Wagers;

use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\WagerOp;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->opsQuery()->orderBy('id')->limit(self::LIMIT)->get();
    }

    /** @return Collection<int, Bet> */
    public function pendingBets(): Collection
    {
        return $this->pendingBetsQuery()->orderBy('placed_at')->limit(self::LIMIT)->get(['id', 'reference', 'wager_reference', 'placed_at']);
    }

    /** @return Collection<int, JackpotEntry> */
    public function pendingJackpotEntries(): Collection
    {
        return $this->pendingEntriesQuery()->orderBy('placed_at')->limit(self::LIMIT)->get(['id', 'reference', 'wager_reference', 'placed_at']);
    }

    /**
     * Exact totals, uncapped (the lists above stop at 200), for the health check.
     *
     * @return array{ops: int, failed_ops: int, oldest_op_at: ?string, pending_bets: int, pending_jackpot_entries: int}
     */
    public function counts(): array
    {
        $ops = $this->opsQuery()->toBase()
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as failed, min(created_at) as oldest', [WagerOp::FAILED])
            ->first();

        return [
            'ops' => (int) ($ops->total ?? 0),
            'failed_ops' => (int) ($ops->failed ?? 0),
            'oldest_op_at' => $ops->oldest ?? null,
            'pending_bets' => $this->pendingBetsQuery()->count(),
            'pending_jackpot_entries' => $this->pendingEntriesQuery()->count(),
        ];
    }

    /** @return Builder<WagerOp> */
    private function opsQuery(): Builder
    {
        return WagerOp::query()->needsOperator();
    }

    /** @return Builder<Bet> */
    private function pendingBetsQuery(): Builder
    {
        return Bet::query()->where('status', BetStatus::Pending)->where('placed_at', '<', $this->alertBefore());
    }

    /** @return Builder<JackpotEntry> */
    private function pendingEntriesQuery(): Builder
    {
        return JackpotEntry::query()->where('status', JackpotEntryStatus::Pending)->where('placed_at', '<', $this->alertBefore());
    }

    private function alertBefore(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->subMinutes((int) config('sportsbook.wagers.pending_alert_minutes'));
    }
}

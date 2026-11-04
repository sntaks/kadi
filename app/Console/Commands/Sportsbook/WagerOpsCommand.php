<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\WagerOp;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:wager-ops
    {--retry= : Put this failed op back in line, once its cause is dealt with}')]
#[Description('The operator queue: sports wager ops the Kadi API refused or that are overdue, and placements still unknown after 10 minutes')]
class WagerOpsCommand extends Command
{
    public function handle(WagerOps $ops): int
    {
        if (($retry = $this->option('retry')) !== null) {
            $op = WagerOp::query()->find((int) $retry);

            if ($op === null || $op->status !== WagerOp::FAILED) {
                $this->error('No failed op has that id.');

                return self::FAILURE;
            }

            $ops->retry($op);
            $this->info("Op {$op->id} is back in line.");

            return self::SUCCESS;
        }

        $alert = now('UTC')->subMinutes((int) config('sportsbook.wagers.pending_alert_minutes'));

        $this->table(
            ['Op', 'Wager', 'Subject', 'Operation', 'Version', 'Status', 'Attempts', 'Last status', 'Last error', 'Created (UTC)'],
            WagerOp::query()->needsOperator()->orderBy('id')->limit(200)->get()->map(fn (WagerOp $op): array => [
                $op->id, $op->product.':'.$op->wager_reference, $op->subject_type.' '.$op->subject_id, $op->operation, $op->version,
                $op->status, $op->attempts, $op->last_status ?? 'no answer', $op->last_error, $op->created_at?->format('Y-m-d H:i'),
            ])->all(),
        );

        $pending = Bet::query()->where('status', BetStatus::Pending)->where('placed_at', '<', $alert)->get(['id', 'reference', 'wager_reference', 'placed_at'])
            ->map(fn (Bet $bet): array => ['bet', $bet->id, $bet->reference, $bet->wager_reference, $bet->placed_at->format('Y-m-d H:i')])
            ->concat(JackpotEntry::query()->where('status', JackpotEntryStatus::Pending)->where('placed_at', '<', $alert)->get(['id', 'reference', 'wager_reference', 'placed_at'])
                ->map(fn (JackpotEntry $entry): array => ['jackpot entry', $entry->id, $entry->reference, $entry->wager_reference, $entry->placed_at->format('Y-m-d H:i')]));

        $this->table(['Pending', 'Id', 'Reference', 'Wager', 'Placed (UTC)'], $pending->all());

        return self::SUCCESS;
    }
}

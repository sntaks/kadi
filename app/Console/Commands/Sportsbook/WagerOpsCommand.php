<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Models\WagerOp;
use App\Sportsbook\Wagers\NeedsOperator;
use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:wager-ops
    {--retry= : Put this failed op back in line, once its cause is dealt with}')]
#[Description('The operator queue: sports wager ops the Kadi API refused or that are overdue, and placements still unknown after 10 minutes')]
class WagerOpsCommand extends Command
{
    public function handle(WagerOps $ops, NeedsOperator $needs): int
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

        $this->table(
            ['Op', 'Wager', 'Subject', 'Operation', 'Version', 'Status', 'Attempts', 'Last status', 'Last error', 'Created (UTC)'],
            $needs->ops()->map(fn (WagerOp $op): array => [
                $op->id, $op->product.':'.$op->wager_reference, $op->subject_type.' '.$op->subject_id, $op->operation, $op->version,
                $op->status, $op->attempts, $op->last_status ?? 'no answer', $op->last_error, $op->created_at?->format('Y-m-d H:i'),
            ])->all(),
        );

        $pending = $needs->pendingBets()
            ->map(fn (Bet $bet): array => ['bet', $bet->id, $bet->reference, $bet->wager_reference, $bet->placed_at->format('Y-m-d H:i')])
            ->toBase()->concat($needs->pendingJackpotEntries()
            ->map(fn (JackpotEntry $entry): array => ['jackpot entry', $entry->id, $entry->reference, $entry->wager_reference, $entry->placed_at->format('Y-m-d H:i')]));

        $this->table(['Pending', 'Id', 'Reference', 'Wager', 'Placed (UTC)'], $pending->all());

        return self::SUCCESS;
    }
}

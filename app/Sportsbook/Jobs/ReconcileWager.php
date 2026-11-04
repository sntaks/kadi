<?php

namespace App\Sportsbook\Jobs;

use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Wagers\WagerPlacement;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Looks up one pending bet or jackpot entry at the Kadi API (GET wagers/{reference}) after its placement got no
 * clear answer: open if the API has it, rejected (behind a void tombstone) if it does not. One attempt per job: while
 * it stays pending, `sportsbook:reconcile-wagers` dispatches it again every minute, and after 10 minutes it is also
 * listed for an operator (`sportsbook:wager-ops`).
 *
 * Carries ids only. Keep the nested limits: timeout 60s < worker --timeout 90s < retry_after 120s.
 */
class ReconcileWager implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    /**
     * @param  'bet'|'jackpot_entry'  $subjectType
     */
    public function __construct(public string $subjectType, public int $subjectId)
    {
        $this->onQueue((string) config('sportsbook.wagers.queue', 'default'));
    }

    public function uniqueId(): string
    {
        return "{$this->subjectType}:{$this->subjectId}";
    }

    public function handle(WagerPlacement $placement): void
    {
        $subject = $this->subjectType === 'bet'
            ? Bet::query()->find($this->subjectId)
            : JackpotEntry::query()->find($this->subjectId);

        if ($subject !== null) {
            $placement->reconcile($subject);
        }
    }
}

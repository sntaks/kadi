<?php

namespace App\Sportsbook\Jobs;

use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the sportsbook's due settlements and reversals to the Kadi API (WagerOps::sendDue). Dispatched after each
 * op is written; the scheduler also runs it every minute, so an op is never stranded by a lost job.
 *
 * Keep the nested limits: timeout 60s < worker --timeout 90s < retry_after 120s. The sender stops taking new ops
 * after 45 seconds.
 */
class SendWagerOps implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 60;

    public function __construct()
    {
        $this->onQueue((string) config('sportsbook.wagers.queue', 'default'));
    }

    public function handle(WagerOps $ops): void
    {
        $ops->sendDue();
    }
}

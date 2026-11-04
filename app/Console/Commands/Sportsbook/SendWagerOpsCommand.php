<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Wagers\WagerOps;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:send-wager-ops')]
#[Description('Send due sports settlements and reversals to the Kadi API, in order per wager')]
class SendWagerOpsCommand extends Command
{
    public function handle(WagerOps $ops): int
    {
        $counts = $ops->sendDue();

        if (array_sum($counts) > 0) {
            $this->info("Sent {$counts['sent']}, retrying {$counts['retrying']}, failed {$counts['failed']}.");
        }

        return self::SUCCESS;
    }
}

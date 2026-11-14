<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Sync\SuspendStaleMarkets;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:suspend-stale')]
#[Description('Suspend open markets whose fixture has kicked off or whose odds are out of date')]
class SuspendStaleMarketsCommand extends Command
{
    public function handle(SuspendStaleMarkets $suspendStaleMarkets): int
    {
        $suspended = $suspendStaleMarkets->handle();

        $this->info("Suspended {$suspended['kicked_off']} kicked-off, {$suspended['stale']} stale, {$suspended['live_stale']} live-stale and {$suspended['late']} late-phase markets.");

        return self::SUCCESS;
    }
}

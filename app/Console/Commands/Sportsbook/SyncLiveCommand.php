<?php

namespace App\Console\Commands\Sportsbook;

use App\Sportsbook\Support\InPlay;
use App\Sportsbook\Sync\SyncLive;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('sportsbook:sync-live
    {--force : Tick now, even if the last tick was less than tick_seconds ago}')]
#[Description('One in-play tick: live scores and prices for matches in play (spends Odds API credits, plan §4.2)')]
class SyncLiveCommand extends Command
{
    public const LAST_TICK_KEY = 'sportsbook.live.last-tick';

    public function handle(SyncLive $syncLive): int
    {
        if (! InPlay::on()) {
            $this->line('In play is off (PLATFORM_SPORTS_IN_PLAY).');

            return self::SUCCESS;
        }

        // Scheduled every 20 s (Laravel has no 40 s frequency): tick only once `tick_seconds` have passed, so the
        // skipped runs cost nothing. A second of slack keeps a 40 s tick from slipping to 60 s.
        $last = Cache::get(self::LAST_TICK_KEY);
        $tick = (int) config('sportsbook.in_play.tick_seconds');

        if (! $this->option('force') && is_numeric($last) && now()->getTimestamp() - (int) $last < $tick - 1) {
            return self::SUCCESS;
        }

        Cache::put(self::LAST_TICK_KEY, now()->getTimestamp(), now()->addHour());
        $counts = $syncLive->handle();

        $this->line("Live tick: {$counts['fixtures']} matches in {$counts['competitions']} competitions.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands\Sportsbook;

use App\Jobs\Sportsbook\SyncCompetitionFixtures;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Sync\SyncCompetitions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sportsbook:sync-fixtures {--sync : Run in this process instead of queueing a job per competition}')]
#[Description('Refresh the competition list and fixtures for synced competitions (free Odds API endpoints)')]
class SyncFixturesCommand extends Command
{
    public function handle(SyncCompetitions $syncCompetitions): int
    {
        $inFeed = $syncCompetitions->handle();
        $this->info("{$inFeed} competitions in the feed.");

        $competitions = Competition::query()->synced()->orderBy('sort_order')->get();

        foreach ($competitions as $competition) {
            $this->option('sync')
                ? SyncCompetitionFixtures::dispatchSync($competition)
                : SyncCompetitionFixtures::dispatch($competition);
        }

        $this->info(($this->option('sync') ? 'Synced' : 'Queued').' fixtures for '.$competitions->count().' competitions.');

        return self::SUCCESS;
    }
}

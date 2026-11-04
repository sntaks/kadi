<?php

namespace App\Jobs\Sportsbook;

use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Sync\SyncOdds;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Buys and applies one competition's odds. Never retried and unique per competition for an hour, because every
 * attempt spends credits.
 */
class SyncCompetitionOdds implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /**
     * @param  list<MarketType>  $marketTypes
     */
    public function __construct(
        public Competition $competition,
        public array $marketTypes,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->competition->id;
    }

    public function handle(SyncOdds $syncOdds): void
    {
        $syncOdds->handle($this->competition, $this->marketTypes);
    }
}

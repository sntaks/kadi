<?php

namespace App\Jobs\Sportsbook;

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Sync\SyncFixtures;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pulls one competition's fixtures. Free, so transient failures are retried.
 */
class SyncCompetitionFixtures implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public Competition $competition) {}

    public function uniqueId(): string
    {
        return (string) $this->competition->id;
    }

    public function handle(SyncFixtures $syncFixtures): void
    {
        $syncFixtures->handle($this->competition);
    }
}

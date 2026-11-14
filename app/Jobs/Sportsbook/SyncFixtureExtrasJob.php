<?php

namespace App\Jobs\Sportsbook;

use App\Sportsbook\Models\Fixture;
use App\Sportsbook\OddsApi\CreditBudgetExceeded;
use App\Sportsbook\Sync\SyncFixtureExtras;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Buys one match's extra markets. Never retried and unique per match for an hour, because every attempt spends
 * credits. Past the `per_event` allowance it logs and stops: the extra lines just go stale and are suspended.
 */
class SyncFixtureExtrasJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    /**
     * @param  list<string>  $markets
     */
    public function __construct(
        public Fixture $fixture,
        public array $markets,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->fixture->id;
    }

    public function handle(SyncFixtureExtras $sync): void
    {
        try {
            $sync->handle($this->fixture, $this->markets);
        } catch (CreditBudgetExceeded $exception) {
            Log::warning("Match extras not bought: {$exception->getMessage()}");
        }
    }
}

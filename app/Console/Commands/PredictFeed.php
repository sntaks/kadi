<?php

namespace App\Console\Commands;

use App\Predict\MarketFeed;
use App\Support\PredictPages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Sleep;

/**
 * The prediction market feed worker: a long-running Supervisor program beside the queue workers and Reverb,
 * restarted on deploy (docs/predictions.md). It ticks `MarketFeed` every few seconds while the prediction pages are
 * on and idles while they are off. It exits after `--max-seconds` so Supervisor starts a fresh process.
 */
class PredictFeed extends Command
{
    protected $signature = 'predict:feed
        {--once : Run one tick and exit}
        {--max-seconds=3600 : Exit after this long; Supervisor starts it again}';

    protected $description = 'Follow the Kadi API prediction market feed and broadcast changes to open pages';

    private bool $running = true;

    public function handle(MarketFeed $feed): int
    {
        if ($this->option('once')) {
            return $this->tick($feed);
        }

        if (extension_loaded('pcntl')) {
            $this->trap([SIGTERM, SIGINT, SIGQUIT], function (): void {
                $this->running = false;
            });
        }

        $stopAt = Date::now()->addSeconds(max(60, (int) $this->option('max-seconds')));
        $interval = max(1, (int) config('predict.feed.interval_seconds', 5));

        while ($this->running && Date::now()->lessThan($stopAt)) {
            $this->tick($feed);
            Sleep::for($interval)->seconds();
        }

        return self::SUCCESS;
    }

    private function tick(MarketFeed $feed): int
    {
        if (! PredictPages::on()) {
            return self::SUCCESS;
        }

        $result = $feed->tick();

        if ($this->output->isVerbose()) {
            $this->line(match (true) {
                ! $result['ran'] => 'Another process is ticking.',
                ! $result['ok'] => "No good answer from the Kadi API; {$result['changed']} market(s) changed.",
                default => "{$result['changed']} market(s) changed.",
            });
        }

        return $result['ran'] && ! $result['ok'] ? self::FAILURE : self::SUCCESS;
    }
}

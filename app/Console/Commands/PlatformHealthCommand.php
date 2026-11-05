<?php

namespace App\Console\Commands;

use App\Support\PlatformHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Sentry\Severity;
use Sentry\State\Scope;

use function Sentry\captureMessage;
use function Sentry\withScope;

/**
 * Runs the platform checks (App\Support\PlatformHealth) and reports each problem to Sentry as its own issue
 * (fingerprint platform-health/<check>, tag health_check), so an alert rule per check can page someone. Without a
 * Sentry DSN the problems are still logged. docs/platform-health.md.
 */
class PlatformHealthCommand extends Command
{
    protected $signature = 'platform:health';

    protected $description = 'Check game launches, sports settlements and the game catalogue, and report problems to Sentry';

    public function handle(PlatformHealth $health): int
    {
        $results = $health->check();
        $problems = array_filter($results);

        foreach ($results as $check => $problem) {
            $this->line(sprintf('%-18s %s', $check, $problem['message'] ?? 'ok'));
        }

        foreach ($problems as $check => $problem) {
            Log::warning('Platform health: '.$problem['message'], ['check' => $check] + $problem['context']);

            withScope(function (Scope $scope) use ($check, $problem): void {
                $scope->setFingerprint(['platform-health', $check]);
                $scope->setTag('health_check', $check);
                $scope->setContext('health', $problem['context']);
                captureMessage($problem['message'], Severity::error());
            });
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}

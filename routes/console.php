<?php

use App\Activity\PlayerActivity;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command('sitemap:generate')->daily();

/*
 * The merged sportsbook (plan Phase 7), only while PLATFORM_SPORTS is on. Times are the standalone app's.
 */
$sports = fn (): bool => (bool) config('platform.sports');

// Free: competition list and fixtures, including kick-off changes and matches in play.
Schedule::command('sportsbook:sync-fixtures')->hourly()->when($sports)->withoutOverlapping(30)->onOneServer();

// Paid: one credit per synced competition per market per region. Once a day on the free plan.
Schedule::command('sportsbook:sync-odds')->dailyAt('09:00')->timezone('Africa/Nairobi')->when($sports)->withoutOverlapping(60)->onOneServer();

// Paid: each match's extra markets (alternate lines, team goal lines), once in each window before kick-off (plan §10.6).
Schedule::command('sportsbook:sync-event-odds')->hourly()->when($sports)->withoutOverlapping(30)->onOneServer();

// Free postponement voids every run; paid scores (2 credits) only for competitions with bets waiting on a result,
// at most every 12 hours each.
Schedule::command('sportsbook:settle')->hourly()->when($sports)->withoutOverlapping(30)->onOneServer();

Schedule::command('sportsbook:suspend-stale')->everyFiveMinutes()->when($sports)->withoutOverlapping(10)->onOneServer();

// Money owed to or by players reaches the Kadi API whatever the switches say: settlements and reversals from the
// outbox, and lookups of bets whose placement got no answer. Both do nothing when there is nothing waiting.
// Problems that need a person (stuck settlements, failing launches, a stale catalogue) reach Sentry: docs/platform-health.md.
Schedule::command('platform:health')->everyFiveMinutes()->withoutOverlapping(10)->onOneServer();
Schedule::command('sportsbook:send-wager-ops')->everyMinute()->withoutOverlapping(5)->onOneServer();
Schedule::command('sportsbook:reconcile-wagers')->everyMinute()->withoutOverlapping(5)->onOneServer();

// The player activity log keeps 90 days (App\Activity\PlayerActivity::RETENTION_DAYS); the security trail keeps the
// package default (config/activitylog.php clean_after_days).
Schedule::command('activitylog:clean', [PlayerActivity::LOG_NAME, '--days='.PlayerActivity::RETENTION_DAYS, '--force'])->dailyAt('03:30')->withoutOverlapping(60)->onOneServer();

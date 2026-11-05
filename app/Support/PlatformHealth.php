<?php

namespace App\Support;

use App\Games\GameCatalogue;
use App\Models\GameLaunch;
use App\Sportsbook\Wagers\NeedsOperator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * What the site checks about itself every few minutes (php artisan platform:health, docs/platform-health.md). Each
 * check answers with a problem or null; the command reports problems to Sentry, one issue per check, so alerts can
 * be set per check. Nothing here names a player, a token or an amount.
 */
class PlatformHealth
{
    /** Launch failures that are the platform's fault. A player's own state (on a break, not linked) is not. */
    public const SYSTEM_LAUNCH_ERRORS = ['unreachable', 'rate_limited', 'game_unavailable', 'bad_launch_url'];

    /** @var array{ops: int, failed_ops: int, oldest_op_at: ?string, pending_bets: int, pending_jackpot_entries: int}|null */
    private ?array $counts = null;

    public function __construct(private NeedsOperator $needsOperator, private GameCatalogue $catalogue) {}

    /**
     * @return array<string, array{message: string, context: array<string, int|float|string>}|null>
     */
    public function check(): array
    {
        $this->counts = null;

        return [
            'game_launches' => $this->launches(),
            'wager_ops' => $this->wagerOps(),
            'wager_placements' => $this->placements(),
            'game_catalogue' => $this->catalogue(),
        ];
    }

    /** @return array{message: string, context: array<string, int|float|string>}|null */
    private function launches(): ?array
    {
        $minutes = (int) config('kadi.health.launch_window_minutes');

        // One pass over the window: every (status, error) pair and its count.
        $rows = GameLaunch::query()->where('created_at', '>=', now()->subMinutes($minutes))
            ->toBase()->selectRaw('status, error, count(*) as n')->groupBy('status', 'error')->get();

        $total = (int) $rows->sum('n');
        $system = $rows->filter(fn ($row) => $row->status === 'failed' && in_array($row->error, self::SYSTEM_LAUNCH_ERRORS, true));
        $failed = (int) $system->sum('n');

        if ($total === 0 || $failed < (int) config('kadi.health.launch_min_failures')) {
            return null;
        }

        $rate = $failed / $total;

        if ($rate < (float) config('kadi.health.launch_max_failure_rate')) {
            return null;
        }

        $reasons = $system->sortByDesc('n')->map(fn ($row) => "{$row->error}: {$row->n}")->implode(', ');

        return [
            'message' => "Game launches are failing: {$failed} of {$total} in the last {$minutes} minutes ({$reasons}).",
            'context' => ['failed' => $failed, 'total' => $total, 'rate' => round($rate, 2), 'minutes' => $minutes],
        ];
    }

    /** Settlements that have not reached the Kadi API: failed ops, or ops waiting too long. */
    private function wagerOps(): ?array
    {
        $counts = $this->counts();

        if ($counts['ops'] === 0) {
            return null;
        }

        $oldest = $counts['oldest_op_at'] === null ? 0 : (int) Date::parse($counts['oldest_op_at'], 'UTC')->diffInMinutes(now('UTC'));

        return [
            'message' => "Sports settlements are stuck: {$counts['ops']} wager ops need an operator ({$counts['failed_ops']} failed).",
            'context' => ['ops' => $counts['ops'], 'failed' => $counts['failed_ops'], 'oldest_minutes' => $oldest],
        ];
    }

    /** Placements whose outcome the Kadi API has still not confirmed after the alert window. */
    private function placements(): ?array
    {
        ['pending_bets' => $bets, 'pending_jackpot_entries' => $entries] = $this->counts();

        if ($bets + $entries === 0) {
            return null;
        }

        return [
            'message' => "Sports placements are unconfirmed: {$bets} bets and {$entries} jackpot entries still pending with the Kadi API.",
            'context' => ['bets' => $bets, 'jackpot_entries' => $entries],
        ];
    }

    /** @return array{ops: int, failed_ops: int, oldest_op_at: ?string, pending_bets: int, pending_jackpot_entries: int} */
    private function counts(): array
    {
        return $this->counts ??= $this->needsOperator->counts();
    }

    /** The lobby serves its last good copy while the API is down; past this long, games may be missing or wrong. */
    private function catalogue(): ?array
    {
        if (! config('platform.token_launch')) {
            return null;
        }

        // The catalogue only asks the API when something reads it: read it here, so a quiet night or a cleared cache
        // is never mistaken for an outage. A failed answer leaves the timestamp where it was.
        $this->catalogue->games();
        $fetchedAt = $this->fetchedAt();

        // No answer on record yet (the first run after the timestamp was introduced, or after a cache clear) while a
        // cached copy is still fresh: ask the API now instead of reporting a copy nobody has checked.
        if ($fetchedAt === null) {
            $this->catalogue->refresh();
            $this->catalogue->games();
            $fetchedAt = $this->fetchedAt();
        }
        $limit = (int) config('kadi.health.catalogue_stale_minutes');

        if ($fetchedAt === null) {
            return [
                'message' => 'The game catalogue could not be fetched from the Kadi API just now, and no earlier answer is on record.',
                'context' => ['minutes' => -1],
            ];
        }

        $minutes = (int) floor((now()->getTimestamp() - $fetchedAt) / 60);

        return $minutes < $limit ? null : [
            'message' => "The game catalogue is stale: the Kadi API last answered {$minutes} minutes ago.",
            'context' => ['minutes' => $minutes],
        ];
    }

    /**
     * When the Kadi API last answered for the catalogue. Redis keeps numbers unserialized, so the stored timestamp
     * comes back as a numeric string there and as an int elsewhere: accept both.
     */
    private function fetchedAt(): ?int
    {
        $value = Cache::get(GameCatalogue::FETCHED_AT_KEY);

        return is_numeric($value) ? (int) $value : null;
    }
}

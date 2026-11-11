<?php

namespace App\Activity;

use App\Models\User;
use App\Support\DeviceName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * The player activity log (activity_log, log_name "player"): what players do on the site, for staff only. GMS reads it
 * through its read-only database user; this site only writes it (docs/activity-log.md).
 *
 * Every event and every property is on an allow-list, so nothing else can reach the table: never amounts, phone
 * numbers, codes, tokens, typed text, booking codes or other players' ids. The IP address and browser go on sign-in
 * events only (security). Writing never throws: a logging failure must not break what the player was doing.
 */
class PlayerActivity
{
    public const LOG_NAME = 'player';

    public const RETENTION_DAYS = 90;

    /** Event => the properties it may carry. */
    public const EVENTS = [
        // Server-side: written by PHP where the thing happens.
        'signed_in' => ['method'],
        'signed_out' => [],
        'sign_in_refused' => [],
        'sign_in_failed' => [],
        'other_device_released' => [],
        'game_launched' => ['game', 'title', 'status', 'reason'],
        'bet_placed' => ['status'],
        'jackpot_entered' => ['status'],
        'deposit_requested' => ['ok'],
        'withdrawal_requested' => ['kind', 'ok'],
        'profile_updated' => ['fields'],
        'account_closed' => [],
        // Client-side: sent by resources/js/site/activity.js through ActivityBeaconController.
        'page_view' => ['path'],
        'click' => ['target', 'path'],
        'game_closed' => ['game', 'title', 'seconds'],
    ];

    /** Events the browser may send; everything else comes from the server only. */
    public const CLIENT_EVENTS = ['page_view', 'click', 'game_closed'];

    /** Events that also store the IP address and browser. */
    private const SECURITY_EVENTS = ['signed_in', 'sign_in_refused', 'sign_in_failed', 'other_device_released'];

    /**
     * @param  array<string, mixed>  $properties  Filtered against EVENTS; unknown keys are dropped.
     */
    public function record(string $event, User $user, array $properties = [], ?Model $subject = null): void
    {
        if (! isset(self::EVENTS[$event])) {
            return;
        }

        try {
            $properties = array_intersect_key($properties, array_flip(self::EVENTS[$event]));

            if (in_array($event, self::SECURITY_EVENTS, true) && app()->bound('request')) {
                $properties['ip'] = request()->ip();
                $properties['device'] = DeviceName::from(request()->userAgent());
            }

            $logger = activity(self::LOG_NAME)->causedBy($user)->event($event)->withProperties($properties);

            if ($subject !== null) {
                $logger->performedOn($subject);
            }

            $logger->log($event);
        } catch (\Throwable $e) {
            Log::warning('Player activity not recorded: '.$e::class);
        }
    }
}

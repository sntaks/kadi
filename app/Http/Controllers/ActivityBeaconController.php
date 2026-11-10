<?php

namespace App\Http\Controllers;

use App\Activity\PlayerActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Batches of page views, tagged clicks and game closes from resources/js/site/activity.js (sent with sendBeacon, so a
 * form post with the CSRF token). Signed-in players only. The browser is not trusted: only known client events and
 * their allow-listed, well-formed properties are kept, and the server stamps who and when. Always 204.
 */
class ActivityBeaconController
{
    public const MAX_EVENTS = 20;

    public function __invoke(Request $request, PlayerActivity $activity): Response
    {
        $events = json_decode((string) $request->input('events'), true);

        if (! is_array($events) || ! array_is_list($events)) {
            return response()->noContent();
        }

        foreach (array_slice($events, 0, self::MAX_EVENTS) as $event) {
            $clean = is_array($event) ? $this->clean($event) : null;

            if ($clean !== null) {
                $activity->record($clean['name'], $request->user(), $clean['properties']);
            }
        }

        return response()->noContent();
    }

    /**
     * @param  array<mixed>  $event
     * @return array{name: string, properties: array<string, string|int>}|null
     */
    private function clean(array $event): ?array
    {
        $name = $event['name'] ?? null;

        if (! is_string($name) || ! in_array($name, PlayerActivity::CLIENT_EVENTS, true)) {
            return null;
        }

        $properties = [];

        foreach (PlayerActivity::EVENTS[$name] as $key) {
            $value = $this->property($key, $event[$key] ?? null);

            if ($value === false) {
                return null;
            }

            if ($value !== null) {
                $properties[$key] = $value;
            }
        }

        return ['name' => $name, 'properties' => $properties];
    }

    /** The cleaned value, null when absent, false when malformed (the whole event is then dropped). */
    private function property(string $key, mixed $value): string|int|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($key) {
            // A path on this site, without its query string or fragment (they can carry codes).
            'path' => is_string($value) && str_starts_with($value, '/') && ! str_starts_with($value, '//')
                ? mb_substr((string) strtok(strtok($value, '?'), '#'), 0, 255)
                : false,
            'target' => is_string($value) && preg_match('/^[a-z0-9][a-z0-9_.:-]{0,63}$/', $value) ? $value : false,
            'game', 'title' => is_string($value) && preg_match('/^[a-z0-9_-]{1,64}$/', $value) ? $value : false,
            'seconds' => is_int($value) && $value >= 0 && $value <= 86400 ? $value : false,
            default => false,
        };
    }
}

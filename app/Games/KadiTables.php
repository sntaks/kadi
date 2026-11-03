<?php

namespace App\Games;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Players seated at the Kadi tables right now, per mode, from the game server's totals endpoint.
 * The only seat counts the site shows: never estimated, never padded.
 *
 * Fresh for 5 minutes, then served stale for up to 15 while it refreshes after the response is
 * sent, so no visitor waits on the game server. A failed refresh keeps the last good counts; with
 * none, `seats()` is null and the page shows no numbers at all rather than zeros.
 */
class KadiTables
{
    public const CACHE_KEY = 'kadi.tables';

    public const LAST_GOOD_KEY = 'kadi.tables.last';

    public const DEFAULT_URL = 'https://gameapi.kadi.online/kadi/get_user_totals.php';

    /** @return array{single: int, tournaments: int, jackpots: int, total: int}|null */
    public function seats(): ?array
    {
        $seats = Cache::flexible(self::CACHE_KEY, [300, 900], function () {
            try {
                $seats = $this->fetch();
                Cache::forever(self::LAST_GOOD_KEY, $seats);

                return $seats;
            } catch (\Throwable $e) {
                // Counts are optional on every page: any failure keeps the last good ones, never a 500.
                Log::warning('Kadi tables: seat counts unavailable, keeping the last good ones', ['error' => class_basename($e)]);

                // [] (not null) is cached too, so a down game server costs one slow request per window.
                return Cache::get(self::LAST_GOOD_KEY) ?? [];
            }
        });

        return is_array($seats) && $seats !== [] ? $seats : null;
    }

    /**
     * The last good counts, without ever calling the game server: for re-renders that must not wait on
     * it (a heart tap on the landing). Null when there have never been any.
     *
     * @return array{single: int, tournaments: int, jackpots: int, total: int}|null
     */
    public function lastGood(): ?array
    {
        $seats = Cache::get(self::LAST_GOOD_KEY);

        return is_array($seats) && $seats !== [] ? $seats : null;
    }

    /** Everyone seated, or null when the counts are unknown. */
    public function total(): ?int
    {
        return $this->seats()['total'] ?? null;
    }

    /**
     * A 200 that does not carry all three totals is a failure, never zeros: it must not replace
     * the last good counts.
     *
     * @return array{single: int, tournaments: int, jackpots: int, total: int}
     */
    private function fetch(): array
    {
        $url = (string) config('games.games.kadi.totals_url') ?: self::DEFAULT_URL;
        $response = Http::timeout(3)->get($url)->throw()->json();

        $seats = [];

        foreach (['single', 'tournaments', 'jackpots'] as $mode) {
            $total = is_array($response) ? ($response[$mode]['total'] ?? null) : null;

            if (! is_numeric($total) || $total < 0) {
                throw new \UnexpectedValueException("The Kadi totals response has no {$mode} total.");
            }

            $seats[$mode] = (int) $total;
        }

        return $seats + ['total' => array_sum($seats)];
    }
}

<?php

namespace App\Sportsbook\OddsApi;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Serves recordings made with `sportsbook:record-odds-api` instead of calling the API, so development never
 * spends credits. Kick-off times move forward by however long ago the recording was made, so recorded fixtures
 * stay upcoming (and recorded in-play fixtures stay in play).
 */
class ReplayOddsApi implements OddsApi
{
    public function __construct(
        private readonly string $recordingsPath,
    ) {}

    public static function recordingFile(string $recordingsPath, string $endpoint, ?string $sportKey = null): string
    {
        return rtrim($recordingsPath, '/\\').DIRECTORY_SEPARATOR.$endpoint.($sportKey !== null ? "-{$sportKey}" : '').'.json';
    }

    public function sports(): array
    {
        /** @var list<array{key: string, group: string, title: string, description: string, active: bool, has_outrights: bool}> */
        return $this->load('sports');
    }

    public function events(string $sportKey): array
    {
        /** @var list<array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string}> */
        return $this->load('events', $sportKey);
    }

    public function odds(string $sportKey, array $markets): array
    {
        $events = $this->load('odds', $sportKey);

        foreach ($events as &$event) {
            foreach ($event['bookmakers'] ?? [] as $bookmakerIndex => $bookmaker) {
                $event['bookmakers'][$bookmakerIndex]['markets'] = array_values(array_filter(
                    $bookmaker['markets'] ?? [],
                    fn (array $market): bool => in_array($market['key'] ?? null, $markets, true),
                ));
            }
        }

        /** @var list<array{id: string, sport_key: string, commence_time: string, home_team: string, away_team: string, bookmakers: list<array{key: string, markets: list<array{key: string, outcomes: list<array{name: string, price: float|int, point?: float|int}>}>}>}> */
        return $events;
    }

    /**
     * The recorded scores. Recorded games had already finished, so they match no replayed (always upcoming) fixture:
     * locally, results are entered in the admin panel.
     */
    public function scores(string $sportKey, int $daysFrom): array
    {
        /** @var list<array{id: string, sport_key: string, commence_time: string, completed: bool, home_team: string, away_team: string, scores: list<array{name: string, score: string}>|null, last_update: string|null}> */
        return $this->load('scores', $sportKey);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function load(string $endpoint, ?string $sportKey = null): array
    {
        $file = self::recordingFile($this->recordingsPath, $endpoint, $sportKey);

        if (! File::exists($file)) {
            if ($sportKey !== null) {
                return [];
            }

            throw new RuntimeException("No Odds API recording at [{$file}]. Run `php artisan sportsbook:record-odds-api` with ODDS_API_DRIVER=live.");
        }

        /** @var array{recorded_at: string, data: list<array<string, mixed>>} $recording */
        $recording = File::json($file);
        $shiftSeconds = (int) Carbon::parse($recording['recorded_at'])->diffInSeconds(now('UTC'));

        return array_map(function (array $item) use ($shiftSeconds): array {
            if (isset($item['commence_time']) && is_string($item['commence_time'])) {
                $item['commence_time'] = Carbon::parse($item['commence_time'])->addSeconds($shiftSeconds)->toIso8601ZuluString();
            }

            return $item;
        }, $recording['data']);
    }
}

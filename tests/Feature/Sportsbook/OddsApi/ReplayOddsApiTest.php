<?php

use App\Sportsbook\OddsApi\OddsApi;
use App\Sportsbook\OddsApi\ReplayOddsApi;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->recordingsPath = storage_path('framework/testing/odds-api-recordings');
    File::deleteDirectory($this->recordingsPath);
    File::ensureDirectoryExists($this->recordingsPath);
    config(['sportsbook.odds_api.driver' => 'replay', 'sportsbook.odds_api.recordings_path' => $this->recordingsPath]);
});

afterEach(function () {
    File::deleteDirectory($this->recordingsPath);
});

function writeRecording(string $path, string $endpoint, ?string $sportKey, string $recordedAt, array $data): void
{
    File::put(ReplayOddsApi::recordingFile($path, $endpoint, $sportKey), json_encode(['recorded_at' => $recordedAt, 'data' => $data]));
}

test('moves recorded kick-off times forward by the time since recording', function () {
    writeRecording($this->recordingsPath, 'events', 'soccer_epl', '2026-10-17T09:00:00Z', oddsApiFixture('events-soccer_epl'));
    $this->travelTo(Date::parse('2026-10-19 09:00:00', 'UTC'));

    $events = app(OddsApi::class)->events('soccer_epl');

    expect(array_column($events, 'commence_time'))->toBe(['2026-10-22T14:00:00Z', '2026-10-23T19:00:00Z']);
});

test('returns only the requested markets and never contacts the API', function () {
    Http::preventStrayRequests();
    writeRecording($this->recordingsPath, 'odds', 'soccer_epl', '2026-10-17T09:00:00Z', oddsApiFixture('odds-soccer_epl'));

    $events = app(OddsApi::class)->odds('soccer_epl', ['h2h']);

    $marketKeys = collect($events)->flatMap(fn (array $event) => collect($event['bookmakers'])->flatMap(fn (array $bookmaker) => array_column($bookmaker['markets'], 'key')))->unique()->values()->all();
    expect($marketKeys)->toBe(['h2h']);
    Http::assertNothingSent();
});

test('treats a competition without a recording as having no fixtures', function () {
    expect(app(OddsApi::class)->events('soccer_never_recorded'))->toBe([]);
});

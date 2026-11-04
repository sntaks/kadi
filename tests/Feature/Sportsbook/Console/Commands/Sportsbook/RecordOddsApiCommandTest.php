<?php

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    useLiveOddsApi();
    $this->recordingsPath = storage_path('framework/testing/odds-api-recordings');
    File::deleteDirectory($this->recordingsPath);
    config(['sportsbook.odds_api.recordings_path' => $this->recordingsPath]);
});

afterEach(function () {
    File::deleteDirectory($this->recordingsPath);
});

test('spends nothing when the credit spend is not confirmed', function () {
    Http::fake();

    $this->artisan('sportsbook:record-odds-api', ['--competition' => ['soccer_epl'], '--markets' => 'h2h,totals'])
        ->expectsConfirmation('Spend about 2 Odds API credits?', 'no')
        ->assertFailed();

    Http::assertNothingSent();
    expect(File::exists($this->recordingsPath))->toBeFalse();
});

test('records sports, fixtures and odds for replay once confirmed', function () {
    $this->travelTo(Date::parse('2026-10-17 12:00:00', 'UTC'));
    Http::fake([
        'odds-api.test/v4/sports/soccer_epl/events*' => Http::response(oddsApiFixture('events-soccer_epl')),
        'odds-api.test/v4/sports/soccer_epl/odds*' => Http::response(oddsApiFixture('odds-soccer_epl'), 200, ['x-requests-last' => '2', 'x-requests-remaining' => '486']),
        'odds-api.test/v4/sports*' => Http::response(oddsApiFixture('sports')),
    ]);

    $this->artisan('sportsbook:record-odds-api', ['--competition' => ['soccer_epl'], '--markets' => 'h2h,totals', '--force' => true])
        ->assertSuccessful();

    $odds = File::json("{$this->recordingsPath}/odds-soccer_epl.json");
    expect(File::exists("{$this->recordingsPath}/sports.json"))->toBeTrue()
        ->and(File::exists("{$this->recordingsPath}/events-soccer_epl.json"))->toBeTrue()
        ->and($odds['recorded_at'])->toBe('2026-10-17T12:00:00Z')
        ->and($odds['data'])->toEqual(oddsApiFixture('odds-soccer_epl'));
});

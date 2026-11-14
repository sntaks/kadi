<?php

use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Sport;
use Spatie\Activitylog\Models\Activity;

/*
 * Plan §10.5 phase 3: switching new competitions on in a live site, without resetting what staff set in GMS.
 */

beforeEach(function () {
    $this->football = Sport::factory()->create(['slug' => 'football', 'name' => 'Football']);
    $this->epl = Competition::factory()->synced()->for($this->football)->create(['odds_api_key' => 'soccer_epl', 'sort_order' => 1]);
    // Switched off by staff in GMS, and moved down.
    $this->laLiga = Competition::factory()->for($this->football)->create(['odds_api_key' => 'soccer_spain_la_liga', 'is_synced' => false, 'sort_order' => 9]);
    // In the feed already (SyncCompetitions), never switched on.
    $this->mls = Competition::factory()->for($this->football)->create(['odds_api_key' => 'soccer_usa_mls', 'name' => 'MLS', 'is_synced' => false, 'sort_order' => 0]);
});

test('switches on only the competitions named, after the existing order, and leaves every other one alone', function () {
    $this->artisan('sportsbook:enable-competitions', ['competition' => ['soccer_usa_mls', 'soccer_epl', 'soccer_fa_cup']])
        ->expectsTable(['Competition', 'Result'], [
            ['soccer_usa_mls', 'switched on (order 10)'],
            ['soccer_epl', 'already on'],
            ['soccer_fa_cup', 'created and switched on (order 11)'],
        ])
        ->assertSuccessful();

    expect($this->mls->fresh())->is_synced->toBeTrue()->sort_order->toBe(10)
        ->and($this->epl->fresh()->sort_order)->toBe(1)
        ->and($this->laLiga->fresh())->is_synced->toBeFalse()->sort_order->toBe(9);

    $faCup = Competition::firstWhere('odds_api_key', 'soccer_fa_cup');
    expect($faCup)->name->toBe('FA Cup')->slug->toBe('fa-cup')->is_synced->toBeTrue()->is_active_in_feed->toBeFalse()
        ->and($faCup->sport->is($this->football))->toBeTrue()
        // Out of season: not synced until the feed lists it.
        ->and(Competition::query()->synced()->pluck('odds_api_key')->sort()->values()->all())->toBe(['soccer_epl', 'soccer_usa_mls']);

    expect(Activity::where('log_name', 'odds')->where('subject_id', $this->mls->id)->exists())->toBeTrue();
});

test('a dry run changes nothing', function () {
    $this->artisan('sportsbook:enable-competitions', ['competition' => ['soccer_usa_mls'], '--dry-run' => true])
        ->expectsTable(['Competition', 'Would be'], [['soccer_usa_mls', 'switched on (order 10)']])
        ->assertSuccessful();

    expect($this->mls->fresh()->is_synced)->toBeFalse()
        ->and(Competition::count())->toBe(3);
});

test('a key no configured sport covers is skipped', function () {
    $this->artisan('sportsbook:enable-competitions', ['competition' => ['tennis_atp_finals']])
        ->expectsTable(['Competition', 'Result'], [['tennis_atp_finals', 'skipped: no configured sport has this prefix']])
        ->assertSuccessful();

    expect(Competition::where('odds_api_key', 'tennis_atp_finals')->exists())->toBeFalse();
});

test('asks for keys when given none', function () {
    $this->artisan('sportsbook:enable-competitions')->expectsOutputToContain('Give competition keys')->assertFailed();
});

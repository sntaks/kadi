<?php

use App\Models\User;
use App\Sportsbook\Betting\ApiSportsWallet;
use App\Sportsbook\Betting\SportsWallet;
use App\Sportsbook\Catalogue\FixtureListing;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Sync\UpsertFixture;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/*
 * Phase 7: the sportsbook merged into Kadi. Kadi runs in Africa/Nairobi; every sb_ datetime is stored in UTC and
 * shown in EAT.
 */

test('the app runs in Nairobi time while sportsbook times are stored in UTC', function () {
    expect(config('app.timezone'))->toBe('Africa/Nairobi')
        ->and(config('sportsbook.timezone'))->toBe('Africa/Nairobi');
});

test('a feed kick-off is stored in UTC and shown in EAT', function () {
    $competition = Competition::factory()->synced()->create();

    $fixture = app(UpsertFixture::class)->handle($competition, [
        'id' => 'evt-tz-1', 'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'commence_time' => '2026-11-07T15:00:00Z',
    ]);

    expect(DB::table('sb_fixtures')->where('id', $fixture->id)->value('kicks_off_at'))->toBe('2026-11-07 15:00:00')
        ->and($fixture->fresh()->kicks_off_at->utc()->format('Y-m-d H:i'))->toBe('2026-11-07 15:00')
        ->and($fixture->fresh()->kicks_off_at->timezone(config('sportsbook.timezone'))->format('D j M, H:i'))->toBe('Sat 7 Nov, 18:00');
});

test('a time written in Nairobi time is stored as the same instant in UTC', function () {
    $fixture = Fixture::factory()->create(['kicks_off_at' => Date::parse('2026-11-07 18:00:00', 'Africa/Nairobi')]);

    expect(DB::table('sb_fixtures')->where('id', $fixture->id)->value('kicks_off_at'))->toBe('2026-11-07 15:00:00');
});

test('"Today" and "Tomorrow" follow the EAT calendar day', function () {
    // It is 22:00 EAT on the 6th: a 21:30 UTC kick-off is already 00:30 EAT on the 7th, so it belongs to Tomorrow.
    $this->travelTo(Date::parse('2026-11-06 19:00:00', 'UTC'));
    $late = pricedFixture(['kicks_off_at' => Date::parse('2026-11-06 21:30:00', 'UTC')]); // 00:30 EAT on the 7th
    $tonight = pricedFixture(['kicks_off_at' => Date::parse('2026-11-06 20:00:00', 'UTC')]); // 23:00 EAT on the 6th

    $listing = app(FixtureListing::class);

    expect($listing->forTab('today', 'h2h')->pluck('id')->all())->toBe([$tonight->id])
        ->and($listing->forTab('tomorrow', 'h2h')->pluck('id')->all())->toBe([$late->id]);
});

test('sports money moves through the Kadi API outbox, never a local wallet', function () {
    expect(app(SportsWallet::class))->toBeInstanceOf(ApiSportsWallet::class);
});

test('the sportsbook schedule runs only while sports is on; money owed is sent whatever the switch says', function (bool $sports) {
    config(['platform.sports' => $sports]);

    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'sportsbook:'));
    $money = fn ($event) => str_contains((string) $event->command, 'wager-ops') || str_contains((string) $event->command, 'reconcile-wagers');

    $live = fn ($event) => str_contains((string) $event->command, 'sync-live');

    expect($events)->toHaveCount(8)
        ->and($events->reject($money)->reject($live)->every(fn ($event) => $event->filtersPass(app())))->toBe($sports)
        ->and($events->filter($money)->every(fn ($event) => $event->filtersPass(app())))->toBeTrue()
        // In play has its own switch (off by default), on top of sports.
        ->and($events->filter($live)->sole()->filtersPass(app()))->toBeFalse();

    config(['platform.sports_in_play' => true]);
    expect($events->filter($live)->sole()->filtersPass(app()))->toBe($sports);
})->with([true, false]);

test('players start with "higher" as their odds change choice', function () {
    expect(User::factory()->create()->fresh()->odds_change_policy)->toBe('higher');
});

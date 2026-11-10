<?php

use App\Activity\PlayerActivity;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;

/*
 * The player activity log (App\Activity\PlayerActivity): staff-only, allow-listed, read by GMS.
 */
beforeEach(fn () => Mail::fake());

function playerLog(): Collection
{
    return Activity::query()->where('log_name', PlayerActivity::LOG_NAME)->orderBy('id')->get();
}

function beacon(array $events): TestResponse
{
    return test()->post(route('activity.beacon'), ['events' => json_encode($events)]);
}

test('signing in and out is logged, with the device on the sign-in only', function () {
    $user = User::factory()->create();

    $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('logout'));

    $log = playerLog();
    expect($log->pluck('event')->all())->toBe(['signed_in', 'signed_out'])
        ->and($log[0]->causer_id)->toBe($user->id)
        ->and($log[0]->properties->only(['method', 'device'])->all())->toBe(['method' => 'password', 'device' => 'Safari on iPhone'])
        ->and($log[0]->properties->has('ip'))->toBeTrue()
        ->and($log[1]->properties->all())->toBe([]);
});

test('a wrong password is logged against the account, and nothing for an unknown e-mail', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong']);

    expect(playerLog()->pluck('event')->all())->toBe(['sign_in_failed'])
        ->and(playerLog()->first()->causer_id)->toBe($user->id);
});

test('only allow-listed events and properties are ever stored', function () {
    $user = User::factory()->create();

    app(PlayerActivity::class)->record('made_up_event', $user);
    app(PlayerActivity::class)->record('deposit_requested', $user, ['ok' => true, 'amount' => 500, 'phone' => '254700000000']);

    expect(playerLog())->toHaveCount(1)
        ->and(playerLog()->first()->properties->all())->toBe(['ok' => true]);
});

test('a player changing their own profile logs the field names, never the values', function () {
    $user = User::factory()->create(['name' => 'Ochieng']);
    $this->actingAs($user);

    $user->update(['name' => 'Wanjiru']);

    expect(playerLog()->sole()->event)->toBe('profile_updated')
        ->and(playerLog()->sole()->properties->all())->toBe(['fields' => ['name']])
        ->and(json_encode(playerLog()->sole()->properties))->not->toContain('Wanjiru');
});

test('changes the system makes are not the player\'s activity', function () {
    $user = User::factory()->create();

    $user->update(['name' => 'Wanjiru']);
    $user->forceFill(['remember_token' => 'x'])->save();

    expect(playerLog())->toHaveCount(0);
});

describe('the browser beacon', function () {
    test('stores page views, tagged clicks and game closes for the signed-in player', function () {
        $user = User::factory()->create();
        $this->actingAs($user);

        beacon([
            ['name' => 'page_view', 'path' => '/casino?promo=SECRET#top'],
            ['name' => 'click', 'target' => 'tile:berry_blast', 'path' => '/casino'],
            ['name' => 'game_closed', 'game' => 'slots', 'title' => 'slots_berry_blast', 'seconds' => 95],
        ])->assertNoContent();

        $log = playerLog();
        expect($log->pluck('event')->all())->toBe(['page_view', 'click', 'game_closed'])
            ->and($log[0]->properties->all())->toBe(['path' => '/casino'])
            ->and($log[1]->properties->all())->toBe(['target' => 'tile:berry_blast', 'path' => '/casino'])
            ->and($log[2]->properties->all())->toBe(['game' => 'slots', 'title' => 'slots_berry_blast', 'seconds' => 95])
            ->and($log->pluck('causer_id')->unique()->all())->toBe([$user->id]);
    });

    test('drops server-only events, unknown events, extra properties and malformed values', function () {
        $this->actingAs(User::factory()->create());

        beacon([
            ['name' => 'signed_in', 'method' => 'password'],
            ['name' => 'bet_placed', 'status' => 'open'],
            ['name' => 'made_up'],
            ['name' => 'page_view', 'path' => 'https://evil.example/x'],
            ['name' => 'page_view', 'path' => '//evil.example/x'],
            ['name' => 'click', 'target' => '<script>'],
            ['name' => 'game_closed', 'game' => 'slots', 'seconds' => -1],
            ['name' => 'page_view', 'path' => '/wallet', 'amount' => 500, 'text' => 'typed'],
        ])->assertNoContent();

        expect(playerLog()->sole()->properties->all())->toBe(['path' => '/wallet']);
    });

    test('keeps at most 20 events per batch and ignores garbage', function () {
        $this->actingAs(User::factory()->create());

        beacon(array_fill(0, 30, ['name' => 'page_view', 'path' => '/']))->assertNoContent();
        $this->post(route('activity.beacon'), ['events' => 'not json'])->assertNoContent();

        expect(playerLog())->toHaveCount(20);
    });

    test('is for signed-in players only', function () {
        beacon([['name' => 'page_view', 'path' => '/']])->assertRedirect(route('login'));

        expect(playerLog())->toHaveCount(0);
    });

    test('is throttled per player', function () {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 30) as $i) {
            beacon([])->assertNoContent();
        }

        beacon([])->assertTooManyRequests();
    });
});

test('the page tells the script where to send events only for signed-in players', function () {
    $this->get('/')->assertDontSee('data-activity-url', false);

    $this->actingAs(User::factory()->create())->get('/')->assertSee('data-activity-url="'.route('activity.beacon').'"', false);
});

test('the player log is pruned after 90 days, apart from the security trail', function () {
    $events = collect(app(Schedule::class)->events())->map->command->filter(fn (?string $command) => str_contains((string) $command, 'activitylog:clean'));

    expect($events->sole())->toContain("'player'")->toContain('--days=90');
});

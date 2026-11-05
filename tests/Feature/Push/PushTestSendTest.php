<?php

use App\Models\User;
use App\Notifications\PushMessageNotification;
use App\Services\PushTestSender;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\WebPushChannel;

function withVapidKeys(): void
{
    config(['webpush.vapid.public_key' => 'BPublicKey', 'webpush.vapid.private_key' => 'PrivateKey']);
}

function withDevice(User $user): void
{
    $user->pushSubscriptions()->create([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.bin2hex(random_bytes(12)),
        'public_key' => 'BKey',
        'auth_token' => 'authtoken',
    ]);
}

function sentReport(int $status, bool $success): MessageSentReport
{
    return new MessageSentReport(new Request('POST', 'https://fcm.googleapis.com/fcm/send/x'), new Response($status), $success);
}

/**
 * Emulates "we are on local/staging" (true) or "we are on production" (false) through config,
 * never by changing the app environment: Laravel only skips CSRF checks in the `testing`
 * environment, so switching it would make every POST here fail with 419.
 */
function onLocalOrStaging(bool $yes = true): void
{
    config(['kadi.push.test_environments' => $yes ? ['testing'] : ['local', 'staging']]);
}

function adminUser(): User
{
    return tap(User::factory()->create())->assignRole('admin');
}

// --- Who may use the button ------------------------------------------------

test('guests cannot send a test push', function () {
    $this->postJson(route('push.test'))->assertUnauthorized();
});

test('an ordinary player cannot send a test push in production', function () {
    onLocalOrStaging(false);
    withVapidKeys();

    $this->actingAs(User::factory()->create())->postJson(route('push.test'))->assertForbidden();
});

test('local and staging allow anyone signed in, admins are allowed everywhere', function (bool $localOrStaging, bool $admin, bool $allowed) {
    onLocalOrStaging($localOrStaging);
    withVapidKeys();

    $user = $admin ? adminUser() : User::factory()->create();

    $this->actingAs($user)->postJson(route('push.test'))->assertStatus($allowed ? 200 : 403);
})->with([
    'local or staging, player' => [true, false, true],
    'local or staging, admin' => [true, true, true],
    'production, player' => [false, false, false],
    'production, admin' => [false, true, true],
]);

// --- Outcomes --------------------------------------------------------------

test('it refuses to run when push is not configured', function () {
    onLocalOrStaging();
    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('push.test'))
        ->assertStatus(503)
        ->assertJsonPath('message', 'Push notifications are not configured on this server.');
});

test('a user with no device is told so, without touching the push channel', function () {
    onLocalOrStaging();
    withVapidKeys();
    $this->mock(WebPushChannel::class)->shouldNotReceive('send');

    $this->actingAs(User::factory()->create())
        ->postJson(route('push.test'))
        ->assertOk()
        ->assertExactJson(['devices' => 0, 'delivered' => 0, 'expired' => 0, 'failed' => 0]);
});

test('it reports delivered, expired and failed devices separately', function () {
    onLocalOrStaging();
    withVapidKeys();

    $user = User::factory()->create();
    foreach (range(1, 3) as $i) {
        withDevice($user);
    }

    $this->mock(WebPushChannel::class, fn ($mock) => $mock->shouldReceive('send')->once()->andReturn([
        sentReport(201, true),
        sentReport(410, false),
        sentReport(500, false),
    ]));

    $this->actingAs($user)
        ->postJson(route('push.test'))
        ->assertOk()
        ->assertExactJson(['devices' => 3, 'delivered' => 1, 'expired' => 1, 'failed' => 1]);
});

test('it only ever pushes to the caller, with a clearly labelled test message', function () {
    onLocalOrStaging();
    withVapidKeys();

    [$caller, $other] = User::factory()->count(2)->create();
    withDevice($caller);
    withDevice($other);

    $this->mock(WebPushChannel::class, fn ($mock) => $mock->shouldReceive('send')
        ->once()
        ->withArgs(fn ($notifiable, $notification) => $notifiable->is($caller)
            && $notification instanceof PushMessageNotification
            && $notification->title === 'Kadi test notification'
            && $notification->tag === 'push-test'
            && $notification->url === '/account#notifications')
        ->andReturn([sentReport(201, true)]));

    $this->actingAs($caller)->postJson(route('push.test'))->assertOk();
});

test('a failure while sending is reported generically and never leaks the exception', function () {
    onLocalOrStaging();
    withVapidKeys();

    $user = User::factory()->create();
    withDevice($user);

    $this->mock(WebPushChannel::class, fn ($mock) => $mock->shouldReceive('send')->andThrow(new RuntimeException('secret-internal-detail')));

    $response = $this->actingAs($user)
        ->postJson(route('push.test'))
        ->assertStatus(502)
        ->assertJsonPath('message', 'The test push could not be sent. Check the server log.')
        ->assertJsonMissingPath('exception');

    expect($response->getContent())->not->toContain('secret-internal-detail');
});

test('test sends are throttled to five a minute per user', function () {
    onLocalOrStaging();
    withVapidKeys();

    $this->actingAs(User::factory()->create());

    foreach (range(1, 5) as $i) {
        $this->postJson(route('push.test'))->assertOk();
    }

    $this->postJson(route('push.test'))->assertStatus(429);
});

// --- The button in the page ------------------------------------------------

test('the send button is only rendered for people allowed to use it', function () {
    onLocalOrStaging(false);

    $player = $this->actingAs(User::factory()->create())->get(route('account'))->getContent();
    expect($player)->not->toContain('data-test="pwa-notifications-test-button"');

    $admin = $this->actingAs(adminUser())->get(route('account'))->getContent();
    expect($admin)->toContain('data-test="pwa-notifications-test-button"')
        ->toContain('data-test="pwa-notifications-test-result"')
        ->toContain('role="status"');

    onLocalOrStaging();
    expect($this->actingAs(User::factory()->create())->get(route('account'))->getContent())
        ->toContain('data-test="pwa-notifications-test-button"');
});

// --- The artisan command ---------------------------------------------------

test('push:test refuses to run without VAPID keys', function () {
    config(['webpush.vapid.public_key' => null, 'webpush.vapid.private_key' => null]);

    $this->artisan('push:test')
        ->expectsOutputToContain('VAPID keys are not set')
        ->assertExitCode(1);
});

test('push:test explains when nobody has a device or the user is unknown', function () {
    withVapidKeys();

    $this->artisan('push:test')->expectsOutputToContain('No user has a registered device')->assertExitCode(1);
    $this->artisan('push:test', ['user' => 'nobody@example.test'])->expectsOutputToContain('No user matches')->assertExitCode(1);
    $this->artisan('push:test', ['user' => '999999'])->expectsOutputToContain('No user matches')->assertExitCode(1);
});

test('push:test defaults to the first user with a device and accepts an id or an email', function () {
    withVapidKeys();

    [$noDevice, $first, $second] = User::factory()->count(3)->create();
    withDevice($first);
    withDevice($second);

    $seen = [];

    // Explicit `use (&$seen)`: an arrow function would capture a copy and the assertion below would see nothing.
    $this->mock(WebPushChannel::class, function ($mock) use (&$seen) {
        $mock->shouldReceive('send')->times(3)->andReturnUsing(function ($notifiable, $notification) use (&$seen) {
            $seen[] = [$notifiable->id, $notification->body];

            return [sentReport(201, true)];
        });
    });

    $this->artisan('push:test')->expectsOutputToContain("user #{$first->id}")->assertExitCode(0);
    $this->artisan('push:test', ['user' => (string) $second->id, '--message' => 'Hello from the CLI'])->assertExitCode(0);
    $this->artisan('push:test', ['user' => $first->email])->assertExitCode(0);

    expect($seen)->toBe([
        [$first->id, 'Push is working on this device.'],
        [$second->id, 'Hello from the CLI'],
        [$first->id, 'Push is working on this device.'],
    ]);
    expect($noDevice->pushSubscriptions()->count())->toBe(0);
});

test('push:test exits non-zero when nothing was delivered and says why', function () {
    withVapidKeys();

    $user = User::factory()->create();
    withDevice($user);

    $this->mock(WebPushChannel::class, fn ($mock) => $mock->shouldReceive('send')->andReturn([sentReport(410, false)]));
    $this->artisan('push:test', ['user' => (string) $user->id])
        ->expectsOutputToContain('had expired and was removed')
        ->assertExitCode(1);

    $this->mock(WebPushChannel::class, fn ($mock) => $mock->shouldReceive('send')->andReturn([sentReport(500, false)]));
    $this->artisan('push:test', ['user' => (string) $user->id])
        ->expectsOutputToContain('rejected the message')
        ->assertExitCode(1);
});

test('push:test points at OPENSSL_CONF when OpenSSL cannot create a key', function () {
    withVapidKeys();

    $user = User::factory()->create();
    withDevice($user);

    $this->mock(WebPushChannel::class, fn ($mock) => $mock->shouldReceive('send')->andThrow(new RuntimeException('Unable to create the key')));

    $this->artisan('push:test', ['user' => (string) $user->id])
        ->expectsOutputToContain('OPENSSL_CONF')
        ->assertExitCode(1);
});

test('the sender limits a long message body to 200 characters', function () {
    withVapidKeys();

    $user = User::factory()->create();
    withDevice($user);

    $captured = null;

    $this->mock(WebPushChannel::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('send')->once()->andReturnUsing(function ($notifiable, $notification) use (&$captured) {
            $captured = $notification->body;

            return [sentReport(201, true)];
        });
    });

    app(PushTestSender::class)->send($user, str_repeat('a', 500));

    // Assert it was captured at all first: a null would otherwise satisfy a length check.
    expect($captured)->toBeString()->and(mb_strlen($captured))->toBe(200);
});

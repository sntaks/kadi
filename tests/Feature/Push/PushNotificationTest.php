<?php

use App\Models\User;
use App\Notifications\PushMessageNotification;
use App\Support\PushEndpoint;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

function subscribeDevice(User $user): void
{
    $user->pushSubscriptions()->create([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.bin2hex(random_bytes(16)),
        'public_key' => 'BKey',
        'auth_token' => 'authtoken',
    ]);
}

// --- Channel selection -----------------------------------------------------

test('a push goes only to users who have a registered device', function () {
    Notification::fake();

    [$withDevice, $without] = User::factory()->count(2)->create();
    subscribeDevice($withDevice);

    $notification = new PushMessageNotification('Hello', 'World');

    $withDevice->notify($notification);
    $without->notify($notification);

    Notification::assertSentTo($withDevice, PushMessageNotification::class, fn ($n, $channels) => $channels === [WebPushChannel::class]);
    Notification::assertNotSentTo($without, PushMessageNotification::class);
});

test('pushes are queued with retries, a backoff and their own timeout', function () {
    Queue::fake();

    $user = User::factory()->create();
    subscribeDevice($user);

    $user->notify(new PushMessageNotification('Hello', 'World'));

    // The job-level values apply on top of whatever flags the production worker is started with.
    Queue::assertPushed(SendQueuedNotifications::class, function ($job) {
        return $job->tries === 3 && $job->backoff() === [10, 60] && $job->timeout === 60;
    });
});

// --- Payload ---------------------------------------------------------------

test('the payload always carries a visible title and body, icons, a ttl and a same-origin url', function () {
    $user = User::factory()->create();
    $notification = new PushMessageNotification('Deposit received', 'Your vault was topped up.', '/wallet', 'deposit', 1800);

    $message = $notification->toWebPush($user, $notification);
    $payload = $message->toArray();

    expect($payload['title'])->toBe('Deposit received')
        ->and($payload['body'])->toBe('Your vault was topped up.')
        ->and($payload['icon'])->toBe('/v2/pwa-icons/icon-192.png')
        ->and($payload['badge'])->toBe('/v2/pwa-icons/badge-72.png')
        ->and($payload['tag'])->toBe('deposit')
        ->and($payload['data'])->toBe(['url' => '/wallet'])
        ->and($message->getOptions())->toBe(['TTL' => 1800, 'urgency' => 'normal']);

    // The message is JSON-safe: exactly what the channel encodes and sends.
    expect(json_decode(json_encode($payload), true))->toBe($payload);
});

test('urgency is passed through when valid and falls back to normal otherwise', function (string $urgency, string $expected) {
    $notification = new PushMessageNotification('Hi', 'There', '/', null, 3600, $urgency);

    expect($notification->toWebPush(User::factory()->make(), $notification)->getOptions()['urgency'])->toBe($expected);
})->with([
    'high' => ['high', 'high'],
    'low' => ['low', 'low'],
    'very-low' => ['very-low', 'very-low'],
    'normal' => ['normal', 'normal'],
    'unknown value' => ['critical', 'normal'],
    'wrong case' => ['HIGH', 'normal'],
    'header injection attempt' => ["high\r\nX-Evil: 1", 'normal'],
]);

test('the tag is omitted when not given', function () {
    $notification = new PushMessageNotification('Hi', 'There');

    expect($notification->toWebPush(User::factory()->make(), $notification)->toArray())->not->toHaveKey('tag');
});

test('targets are reduced to a same-origin path', function (string $target, string $expected) {
    config(['app.url' => 'https://kadi.test']);

    $notification = new PushMessageNotification('Hi', 'There', $target);

    expect($notification->toWebPush(User::factory()->make(), $notification)->toArray()['data']['url'])->toBe($expected);
})->with([
    'a path' => ['/wallet', '/wallet'],
    'a path with a query' => ['/wallet?tab=history', '/wallet?tab=history'],
    'an absolute same-origin url' => ['https://kadi.test/wallet?x=1#top', '/wallet?x=1#top'],
    'same host, different case' => ['https://KADI.test/profile', '/profile'],
    'another host' => ['https://evil.example/phish', '/'],
    'a lookalike host' => ['https://kadi.test.evil.example/x', '/'],
    'a different scheme' => ['http://kadi.test/wallet', '/'],
    'a different port' => ['https://kadi.test:8443/wallet', '/'],
    'protocol-relative' => ['//evil.example/x', '/'],
    'backslash trick' => ['/\\evil.example', '/'],
    'javascript scheme' => ['javascript:alert(1)', '/'],
    'a bare word' => ['wallet', '/'],
    'empty' => ['', '/'],
    'control characters' => ["/wallet\r\nX-Injected: 1", '/'],
]);

// --- Failure logging -------------------------------------------------------

function pushFailure(int $status): NotificationFailed
{
    $endpoint = 'https://fcm.googleapis.com/fcm/send/secret-endpoint-token';
    $report = new MessageSentReport(new Request('POST', $endpoint), new Response($status), false, "$status reason that echoes $endpoint");
    $subscription = new PushSubscription(['endpoint' => $endpoint, 'public_key' => 'secret-p256dh', 'auth_token' => 'secret-auth']);
    $subscription->forceFill(['id' => 7, 'subscribable_id' => 3]);

    return new NotificationFailed($report, $subscription, new WebPushMessage);
}

test('a failed delivery is logged once, without the endpoint, keys or reason', function () {
    $entries = [];
    Log::listen(function ($event) use (&$entries) {
        if (str_starts_with($event->message, 'Web push')) {
            $entries[] = $event;
        }
    });

    event(pushFailure(500));

    expect($entries)->toHaveCount(1); // a duplicate registration would make this 2

    $entry = $entries[0];
    expect($entry->level)->toBe('warning')
        ->and($entry->context)->toBe([
            'subscription_id' => 7,
            'subscribable_id' => 3,
            'push_service' => 'fcm.googleapis.com',
            'status' => 500,
        ]);

    $serialized = json_encode([$entry->message, $entry->context]);
    expect($serialized)->not->toContain('secret-endpoint-token')
        ->not->toContain('secret-p256dh')
        ->not->toContain('secret-auth')
        ->not->toContain('reason');
});

test('an expired subscription is logged quietly at info level', function () {
    $entries = [];
    Log::listen(function ($event) use (&$entries) {
        if (str_starts_with($event->message, 'Web push')) {
            $entries[] = $event;
        }
    });

    event(pushFailure(410));

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->level)->toBe('info')
        ->and($entries[0]->message)->toContain('expired');
});

// --- Endpoint allow-list ---------------------------------------------------

test('only known push services are accepted as endpoints', function (mixed $url, bool $allowed) {
    expect(PushEndpoint::isAllowed($url))->toBe($allowed);
})->with([
    'chrome / fcm' => ['https://fcm.googleapis.com/fcm/send/abc', true],
    'firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/abc', true],
    'edge / wns' => ['https://wns2-par02p.notify.windows.com/w/?token=abc', true],
    'safari' => ['https://web.push.apple.com/QAbc', true],
    'host is case-insensitive' => ['https://FCM.GOOGLEAPIS.COM/fcm/send/abc', true],
    'trailing dot' => ['https://fcm.googleapis.com./fcm/send/abc', true],
    'plain http' => ['http://fcm.googleapis.com/fcm/send/abc', false],
    'unknown host' => ['https://example.com/push', false],
    'localhost' => ['https://localhost/x', false],
    'private ip' => ['https://10.0.0.5/x', false],
    'link-local metadata ip' => ['https://169.254.169.254/latest/meta-data', false],
    'allowed name as a subdomain of an attacker' => ['https://fcm.googleapis.com.attacker.example/x', false],
    'allowed name in the path' => ['https://attacker.example/fcm.googleapis.com', false],
    'allowed name in the userinfo' => ['https://fcm.googleapis.com@attacker.example/x', false],
    'credentials' => ['https://user:pass@fcm.googleapis.com/x', false],
    'other port' => ['https://fcm.googleapis.com:8443/x', false],
    'explicit default port' => ['https://fcm.googleapis.com:443/x', true],
    'ftp' => ['ftp://fcm.googleapis.com/x', false],
    'empty string' => ['', false],
    'null' => [null, false],
    'an array' => [['https://fcm.googleapis.com/x'], false],
]);

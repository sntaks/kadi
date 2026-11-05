<?php

use App\Events\PasswordChanged;
use App\Mail\SecurityAlertEmail;
use App\Models\User;
use App\Notifications\PushMessageNotification;
use App\Notifications\SecurityAlert;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

function registerDevice(User $user): void
{
    $user->pushSubscriptions()->create([
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.bin2hex(random_bytes(12)),
        'public_key' => 'BKey',
        'auth_token' => 'authtoken',
    ]);
}

test('a security change also pushes to users who turned notifications on', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();
    registerDevice($user);

    event(new PasswordChanged($user));

    // The existing in-app alert and email are untouched...
    Notification::assertSentTo($user, SecurityAlert::class);
    Mail::assertQueued(SecurityAlertEmail::class);

    // ...and the push carries the same non-secret text, at high urgency, without a tag.
    Notification::assertSentTo($user, PushMessageNotification::class, function (PushMessageNotification $push) {
        return $push->title === 'Security alert'
            && $push->body === 'Your account password was changed'
            && $push->url === '/account#security'
            && $push->urgency === 'high'
            && $push->tag === null;
    });
});

test('users without a registered device get no push, only the existing alerts', function () {
    Notification::fake();
    Mail::fake();

    $user = User::factory()->create();

    event(new PasswordChanged($user));

    Notification::assertSentTo($user, SecurityAlert::class);
    Mail::assertQueued(SecurityAlertEmail::class);
    Notification::assertNotSentTo($user, PushMessageNotification::class);
});

test('a push is only sent to the account that changed, never another user', function () {
    Notification::fake();
    Mail::fake();

    [$changed, $bystander] = User::factory()->count(2)->create();
    registerDevice($changed);
    registerDevice($bystander);

    event(new PasswordChanged($changed));

    Notification::assertSentTo($changed, PushMessageNotification::class);
    Notification::assertNotSentTo($bystander, PushMessageNotification::class);
});

test('the push text never contains secrets, only the description of the change', function () {
    $user = User::factory()->create();
    $push = new PushMessageNotification('Security alert', 'A new passkey was added to your account', '/account#security', null, 21600, 'high');
    $payload = $push->toWebPush($user, $push)->toArray();

    expect(json_encode($payload))->not->toContain($user->email)->not->toContain($user->name)->not->toContain('password');
    expect($push->toWebPush($user, $push)->getOptions())->toBe(['TTL' => 21600, 'urgency' => 'high']);
});

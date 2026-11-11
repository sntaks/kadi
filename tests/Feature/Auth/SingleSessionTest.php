<?php

use App\Auth\SignedInElsewhere;
use App\Auth\SingleSession;
use App\Mail\ReleaseDeviceEmail;
use App\Mail\SecurityAlertEmail;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/*
 * One signed-in device per player (App\Auth\SingleSession, enforced by App\Auth\SingleSessionGuard). Needs the database
 * session driver, as in production; device A is a row in `sessions`.
 */
beforeEach(function () {
    config(['session.driver' => 'database', 'auth.single_session' => true]);
    Mail::fake();
});

function signedInElsewhere(User $user, int $minutesAgo = 1): void
{
    DB::table('sessions')->insert([
        'id' => 'device-a-'.$user->id,
        'user_id' => $user->id,
        'ip_address' => null,
        'user_agent' => null,
        'payload' => '',
        'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ]);
}

function signIn(User $user): TestResponse
{
    return test()->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
}

test('a player signed in on another device is refused, and offered the sign-out link', function () {
    $user = User::factory()->create();
    signedInElsewhere($user);

    signIn($user)->assertRedirect(route('login'))->assertSessionHasErrors(['email' => SignedInElsewhere::MESSAGE]);

    $this->assertGuest();
    expect(session(SingleSession::PENDING_KEY))->toBe($user->id);
    $this->get(route('login'))->assertSee('E-mail me a sign-out link');
    Mail::assertQueued(SecurityAlertEmail::class, fn (SecurityAlertEmail $mail) => str_contains($mail->change, 'stopped because you are signed in on another device'));
});

test('a player can sign in once the other session has gone idle or signed out', function (Closure $otherDevice) {
    $user = User::factory()->create();
    $otherDevice($user);

    signIn($user)->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
})->with([
    'idle past the timeout' => fn () => fn (User $user) => signedInElsewhere($user, minutesAgo: config('auth.idle_timeout') + 1),
    'signed out (no session)' => fn () => fn (User $user) => null,
]);

test('another player being signed in elsewhere does not matter', function () {
    $user = User::factory()->create();
    signedInElsewhere(User::factory()->create());

    signIn($user)->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('every way of signing in goes through the guard, so Google and passkeys are refused too', function () {
    $user = User::factory()->create();
    signedInElsewhere($user);

    expect(fn () => Auth::guard('web')->login($user))->toThrow(SignedInElsewhere::class);
    expect(Auth::guard('web')->check())->toBeFalse();
});

test('the passkey call (JSON) gets the refusal as a 422 it can show', function () {
    $user = User::factory()->create();
    signedInElsewhere($user);

    $this->postJson(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', SignedInElsewhere::MESSAGE);
});

test('a remember-me cookie does not sign a player in while another device holds their session', function () {
    $user = User::factory()->create();
    $user->setRememberToken('remembered-token');
    $user->save();
    signedInElsewhere($user);

    $guard = Auth::guard('web');
    request()->cookies->set($guard->getRecallerName(), $user->id.'|remembered-token|'.$user->getAuthPassword());

    expect($guard->user())->toBeNull()->and($guard->viaRemember())->toBeFalse();
});

test('a new sign-in retires the remember-me cookies of other devices', function () {
    $user = User::factory()->create();
    $user->setRememberToken('old-device-token');
    $user->save();

    signIn($user)->assertSessionHasNoErrors();

    expect($user->fresh()->getRememberToken())->not->toBe('old-device-token');
});

test('the rule is off without the database session driver', function () {
    config(['session.driver' => 'array']);
    $user = User::factory()->create();
    signedInElsewhere($user);

    expect(app(SingleSession::class)->signedInElsewhere($user, null))->toBeFalse();
});

describe('the e-mailed sign-out link', function () {
    test('signs the other device out, once', function () {
        $user = User::factory()->create();
        signedInElsewhere($user);
        signIn($user);

        $this->post(route('login.release-device'))->assertRedirect(route('login'))->assertSessionHas('status');

        $url = null;
        Mail::assertQueued(ReleaseDeviceEmail::class, function (ReleaseDeviceEmail $mail) use ($user, &$url) {
            $url = $mail->url;

            return $mail->hasTo($user->email);
        });

        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('status', 'Your other device is signed out. You can sign in now.');
        expect(DB::table('sessions')->where('id', 'device-a-'.$user->id)->exists())->toBeFalse();

        signIn($user)->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);

        Auth::logout();
        $this->get($url)->assertSessionHasErrors('email');
    });

    test('is only sent to a player the sign-in checks just refused', function () {
        $this->post(route('login.release-device'))->assertRedirect(route('login'))->assertSessionHas('status');

        Mail::assertNotQueued(ReleaseDeviceEmail::class);
    });

    test('does not work without its signature', function () {
        $this->get('/login/release-device/'.str_repeat('a', 48))->assertForbidden();
    });
});

describe('sign-in alerts', function () {
    test('a new sign-in is announced with the browser and the way in', function () {
        $user = User::factory()->create();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/131.0 Mobile Safari/537.36');
        signIn($user);

        Mail::assertQueued(SecurityAlertEmail::class, fn (SecurityAlertEmail $mail) => $mail->change === 'New sign-in to your account with your password from Chrome on Android');
        expect($user->notifications()->count())->toBe(1);
    });

    test('wrong passwords are announced at most once per 15 minutes', function () {
        $user = User::factory()->create();

        foreach (range(1, 3) as $attempt) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong']);
        }

        Mail::assertQueued(SecurityAlertEmail::class, 1);
        Mail::assertQueued(SecurityAlertEmail::class, fn (SecurityAlertEmail $mail) => str_contains($mail->change, 'wrong password'));
    });

    test('a wrong password for an e-mail without an account sends nothing', function () {
        $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'wrong']);

        Mail::assertNothingQueued();
    });
});

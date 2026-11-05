<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

function fakeGoogleIdentity(string $id = 'google-new-1', string $email = 'newbie@example.com'): void
{
    $googleUser = new GoogleUser;
    $googleUser->id = $id;
    $googleUser->email = $email;
    $googleUser->name = 'New Player';
    $googleUser->avatar = 'https://example.com/avatar.png';

    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('user')->andReturn($googleUser);
}

function loginWithPassword($test, User $user): void
{
    $test->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
}

// --- Google sign-up -------------------------------------------------------

test('a new google user is not created until they consent', function () {
    Queue::fake();
    fakeGoogleIdentity();

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('auth.google.complete'));

    $this->assertGuest();
    expect(User::where('email', 'newbie@example.com')->exists())->toBeFalse();

    $this->get(route('auth.google.complete'))->assertOk();
});

test('a new google user is created and logged in once they consent', function () {
    Queue::fake();
    fakeGoogleIdentity();

    $this->get(route('auth.google.callback'));

    $this->post(route('auth.google.complete.store'), ['age_confirmed' => '1', 'terms' => '1'])
        ->assertRedirect(route('home'));

    $user = User::where('email', 'newbie@example.com')->firstOrFail();

    expect($user->google_id)->toBe('google-new-1')
        ->and($user->hasCurrentConsent())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('google sign-up is rejected without age confirmation or terms', function (array $payload, array $errors) {
    Queue::fake();
    fakeGoogleIdentity();

    $this->get(route('auth.google.callback'));

    $this->post(route('auth.google.complete.store'), $payload)->assertSessionHasErrors($errors);

    $this->assertGuest();
    expect(User::where('email', 'newbie@example.com')->exists())->toBeFalse();
})->with([
    'no age' => [['terms' => '1'], ['age_confirmed']],
    'no terms' => [['age_confirmed' => '1'], ['terms']],
    'neither' => [[], ['age_confirmed', 'terms']],
]);

test('the google completion page requires a pending sign-up', function () {
    $this->get(route('auth.google.complete'))->assertRedirect(route('login'));
    $this->post(route('auth.google.complete.store'), ['age_confirmed' => '1', 'terms' => '1'])
        ->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
});

test('cancelling google sign-up discards the pending identity', function () {
    Queue::fake();
    fakeGoogleIdentity();

    $this->get(route('auth.google.callback'));
    $this->get(route('auth.google.complete.cancel'))->assertRedirect(route('register'));

    $this->get(route('auth.google.complete'))->assertRedirect(route('login'));
    expect(User::count())->toBe(0);
});

// --- Existing users: prompted at next login -------------------------------

test('an existing user without consent is sent to the consent page at login', function () {
    $user = User::factory()->withoutConsent()->create();

    loginWithPassword($this, $user);

    $this->assertAuthenticatedAs($user);
    $this->get(route('wallet'))->assertRedirect(route('consent.show'));
    $this->get(route('consent.show'))->assertOk();
});

test('a legacy user already signed in is not interrupted mid-session', function () {
    $user = User::factory()->withoutConsent()->create();

    $this->actingAs($user)->get(route('wallet'))->assertOk();
});

test('an existing google user without consent is prompted after google login', function () {
    Queue::fake();

    $user = User::factory()->withoutConsent()->create(['google_id' => 'google-legacy']);
    fakeGoogleIdentity('google-legacy', $user->email);

    $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

    $this->get(route('wallet'))->assertRedirect(route('consent.show'));
});

test('consenting clears the gate and returns the user to where they were going', function () {
    $user = User::factory()->withoutConsent()->create();
    loginWithPassword($this, $user);

    $this->get(route('wallet'))->assertRedirect(route('consent.show'));

    $this->post(route('consent.store'), ['age_confirmed' => '1', 'terms' => '1'])
        ->assertRedirect(route('wallet'));

    expect($user->fresh()->hasCurrentConsent())->toBeTrue();
    $this->get(route('wallet'))->assertOk();
});

test('the consent page rejects a submission missing either checkbox', function () {
    $user = User::factory()->withoutConsent()->create();
    loginWithPassword($this, $user);

    $this->post(route('consent.store'), ['terms' => '1'])->assertSessionHasErrors('age_confirmed');
    $this->post(route('consent.store'), ['age_confirmed' => '1'])->assertSessionHasErrors('terms');

    expect($user->fresh()->hasCurrentConsent())->toBeFalse();
});

test('the terms and privacy pages and logout stay reachable while consent is pending', function () {
    $user = User::factory()->withoutConsent()->create();
    loginWithPassword($this, $user);

    $this->get(route('legal.terms'))->assertOk();
    $this->get(route('legal.privacy'))->assertOk();

    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

test('state-changing requests are blocked while consent is pending', function () {
    $user = User::factory()->withoutConsent()->create();
    loginWithPassword($this, $user);

    $this->post(route('profile.picture'))->assertForbidden();
});

test('bumping the terms version re-prompts users at their next login', function () {
    $user = User::factory()->create();

    config(['kadi.terms_version' => 'a-newer-version']);

    loginWithPassword($this, $user);

    $this->get(route('wallet'))->assertRedirect(route('consent.show'));
});

test('a user with current consent is never prompted', function () {
    $user = User::factory()->create();

    loginWithPassword($this, $user);

    $this->get(route('wallet'))->assertOk();
    $this->get(route('consent.show'))->assertRedirect(route('home'));
});

<?php

use App\Http\Middleware\RememberReturnPath;
use App\Models\User;
use App\Promotions\PromoCode;
use App\Referrals\ReferralCode;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/*
 * Phase 6b: sign-in inside the site (the sheet), the auth and gate pages as in-site panels, and the help
 * pages in the reading column.
 */

beforeEach(function () {
    config(['platform.ui_v2' => true]);
    Http::fake();
});

// ─── Return to the page the sheet opened on ─────────────────────────────────

test('only same-site paths that are not sign-in pages can be returned to', function (string $path, bool $safe) {
    expect(RememberReturnPath::isSafe($path))->toBe($safe);
})->with([
    'a page' => ['/wallet', true],
    'a page with a query' => ['/casino/slots?x=1', true],
    'another host' => ['https://evil.example/', false],
    'protocol-relative' => ['//evil.example/x', false],
    'backslash trick' => ['/\\evil.example', false],
    'the login page' => ['/login', false],
    'the register page' => ['/register?ref=X', false],
    'a reset link' => ['/reset-password/abc', false],
    'a control character' => ["/wallet\n", false],
    'no leading slash' => ['wallet', false],
    'a Livewire endpoint' => ['/livewire-0a7e1d40/update', false],
    'the Google redirect' => ['/auth/google', false],
    'a game page' => ['/play/slots/berry-blast', true],
]);

test('logging in from the sheet lands back on the page it opened on', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', '_sheet' => 'login', 'return_to' => '/casino'])
        ->assertRedirect(url('/casino'));
});

test('an unsafe return path is ignored and login lands on home', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password', 'return_to' => '//evil.example/x'])
        ->assertRedirect(route('home'));
});

test('joining from the sheet lands back on the page it opened on', function () {
    Queue::fake();

    $this->post(route('register.store'), [
        'name' => 'JohnDoe', 'email' => 'john@example.com', 'phone' => '0700123456',
        'password' => 'password', 'password_confirmation' => 'password', 'age_confirmed' => '1', 'terms' => '1',
        '_sheet' => 'join', 'return_to' => '/faq',
    ])->assertSessionHasNoErrors()->assertRedirect(url('/faq'));
});

test('Google sign-in from the sheet remembers the page too', function () {
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $this->get(route('auth.google', ['return_to' => '/wallet']));

    expect(session('url.intended'))->toBe(url('/wallet'));
});

// ─── The sheet ───────────────────────────────────────────────────────────────

test('guests get the sign-in sheet on site pages, with all three forms posting to Fortify', function () {
    $html = $this->get(route('faq'))->assertOk()->getContent();

    expect($html)->toContain('data-sheet="signin"')
        ->and($html)->toContain('action="'.route('login.store').'"')
        ->and($html)->toContain('action="'.route('register.store').'"')
        ->and($html)->toContain('action="'.route('password.email').'"')
        ->and($html)->toContain('name="return_to" value="/faq"')
        ->and($html)->toContain('data-signin="login"')
        ->and($html)->toContain('data-signin="join"');
});

test('the sheet is not shown to players, on the sign-in pages themselves, or without the new look', function (string $who) {
    if ($who === 'flag off') {
        config(['platform.ui_v2' => false]);
    }

    $response = match ($who) {
        'player' => $this->actingAs(User::factory()->create())->get(route('faq')),
        'login page' => $this->get(route('login')),
        default => $this->get(route('faq')),
    };

    $response->assertOk()->assertDontSee('data-sheet="signin"', false);
})->with(['player', 'login page', 'flag off']);

test('a failed log in reopens the sheet on the login form, with the error there only', function () {
    $user = User::factory()->create();

    $this->from(route('faq'))->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong', '_sheet' => 'login', 'return_to' => '/faq'])
        ->assertRedirect(route('faq'));

    $html = $this->get(route('faq'))->getContent();
    $message = __('auth.failed');

    expect($html)->toContain('open: true')
        ->and($html)->toContain("tab: 'login'")
        ->and(substr_count($html, e($message)))->toBe(1);
});

test('a sent reset link reopens the sheet on the forgot form with the confirmation', function () {
    $user = User::factory()->create();

    $this->from(route('faq'))->post(route('password.email'), ['email' => $user->email, '_sheet' => 'forgot']);

    $html = $this->get(route('faq'))->getContent();

    expect($html)->toContain("tab: 'forgot'")->and($html)->toContain('open: true');
});

test('the join form keeps the referral and promo codes and the consent', function () {
    config(['kadi.referrals.enabled' => true, 'kadi.promotions.enabled' => true]);

    $sheet = $this->get(route('faq'))->getContent();
    $page = $this->get(route('register'))->getContent();

    expect($sheet)->toContain('name="referral_code"')->and($sheet)->toContain('name="age_confirmed"')->and($sheet)->toContain('name="terms"')
        ->and($page)->toContain('name="promo_code"');
});

test('the hidden sheet never asks KadiApi about a pending promo or referral code', function () {
    config(['kadi.referrals.enabled' => true, 'kadi.promotions.enabled' => true]);

    $this->withSession([PromoCode::SESSION_KEY => 'KADI20', ReferralCode::SESSION_KEY => 'FRIEND1'])
        ->get(route('faq'))->assertOk()->assertSee('value="FRIEND1"', false);

    Http::assertNothingSent();
});

test('a failed attempt forgets its return path, so a later login lands on home', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong', 'return_to' => '/casino']);
    expect(session('url.intended'))->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('home'));
});

test('a failed attempt keeps an intended page the site set itself', function () {
    $user = User::factory()->create();
    $this->get('/wallet')->assertRedirect(route('login')); // the auth middleware remembers /wallet

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong', 'return_to' => '/casino']);

    expect(session('url.intended'))->toBe(url('/wallet'));
});

test('a cancelled Google sign-up forgets the page it started on', function () {
    Socialite::shouldReceive('driver')->with('google')->andReturnSelf();
    Socialite::shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $this->get(route('auth.google', ['return_to' => '/wallet']));
    $this->get(route('auth.google.complete.cancel'));

    expect(session('url.intended'))->toBeNull();
});

test('a guest\'s account-only links and play buttons are marked for the sheet', function () {
    $this->get(route('faq'))->assertSee('data-needs-account', false);
});

// ─── Sign-in and gate pages ──────────────────────────────────────────────────

test('the sign-in pages are in-site panels in the new look and the old pages without it', function (string $route) {
    $this->get(route($route))->assertOk()->assertSee('data-shell="panel"', false)->assertSee('class="nt-field"', false)
        ->assertDontSee("font-family: 'Cinzel'", false);

    config(['platform.ui_v2' => false]);

    $this->get(route($route))->assertOk()->assertDontSee('data-shell=', false);
})->with(['login', 'register', 'password.request']);

test('the reset password page is an in-site panel', function () {
    $this->get(route('password.reset', ['token' => 'abc', 'email' => 'a@example.com']))->assertOk()
        ->assertSee('data-shell="panel"', false)->assertSee('name="token" value="abc"', false);
});

test('the gates are in-site panels with the menu held', function () {
    $user = User::factory()->withoutConsent()->create();

    $this->actingAs($user)->get(route('consent.show'))->assertOk()
        ->assertSee('Before you continue')->assertSee('data-test="consent-button"', false)
        ->assertSee('class="nt-side nt-gated"', false);
});

test('the e-mail and password-confirm gates use the new look', function () {
    $this->actingAs(User::factory()->unverified()->create())->get(route('verification.notice'))->assertOk()
        ->assertSee('Check your e-mail')->assertSee('data-shell="panel"', false);

    $this->actingAs(User::factory()->create())->get(route('password.confirm'))->assertOk()
        ->assertSee('Confirm your password')->assertSee('data-test="confirm-password-button"', false);
});

// ─── Help pages ──────────────────────────────────────────────────────────────

test('the help pages read in one column with a contents list', function (string $route, string $heading) {
    $html = $this->get(route($route))->assertOk()->getContent();

    expect($html)->toContain('class="nt-read')
        ->and($html)->toContain('class="nt-prose"')
        ->and($html)->toContain($heading)
        ->and($html)->not->toContain('noindex')
        ->and(Str::between($html, '<main id="main"', '</main>'))->not->toContain("font-family: 'Cinzel'");
})->with([
    'how to play' => ['rules', 'Empty your hand first'],
    'games and prizes' => ['game-guide', 'KSh 167,772,160'],
    'terms' => ['legal.terms', 'The short version'],
    'privacy' => ['legal.privacy', 'The short version'],
    'faq' => ['faq', 'Kadi FAQ'],
]);

test('the contents list links to every section of the rules', function () {
    $html = $this->get(route('rules'))->getContent();

    foreach (['objective', 'setup', 'card-types', 'chaining', 'gameplay', 'winning', 'strategy'] as $id) {
        expect($html)->toContain('href="#'.$id.'"')->and($html)->toContain('id="'.$id.'"');
    }
});

test('the FAQ keeps its structured data and reads in one column without a contents list', function () {
    $this->get(route('faq'))->assertSee('"@type":"FAQPage"', false)->assertSee('class="nt-read nt-read-solo"', false);
});

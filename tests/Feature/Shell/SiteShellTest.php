<?php

use App\Models\User;
use App\Support\SiteNavigation;
use Illuminate\Support\Facades\Http;

/*
 * Phase 4: every page opens in one shell (layouts/site.blade.php) behind PLATFORM_UI_V2, and in the old
 * layouts with the flag off. The new shell marks <body data-shell="…">; the old layouts never do.
 */

beforeEach(function () {
    // Pages load their API data after render; only the game catalogue is read while the shell renders.
    $this->games = [];
    Http::preventStrayRequests();
    Http::fake(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/games')
        ? Http::response(['data' => $this->games], 200)
        : Http::response([], 200));
});

dataset('guest pages', [
    'landing' => ['home', 'app'],
    'how to play' => ['rules', 'page'],
    'games and prizes' => ['game-guide', 'page'],
    'faq' => ['faq', 'page'],
    'terms' => ['legal.terms', 'page'],
    'privacy' => ['legal.privacy', 'page'],
    'log in' => ['login', 'panel'],
    'join' => ['register', 'panel'],
    'forgot password' => ['password.request', 'panel'],
]);

dataset('player pages', [
    'profile' => ['profile', 'app'],
    'wallet' => ['wallet', 'app'],
    'game history' => ['games.history', 'app'],
    'settings profile' => ['profile.edit', 'app'],
    'settings appearance' => ['appearance.edit', 'app'],
    'confirm password' => ['password.confirm', 'panel'],
]);

test('with the new UI on, a public page opens in the site shell', function (string $route, string $shell) {
    config(['platform.ui_v2' => true]);

    $this->get(route($route))->assertOk()
        ->assertSee('data-shell="'.$shell.'"', false)
        ->assertSee('id="site-menu"', false)
        ->assertSee('class="nt-footer"', false);
})->with('guest pages');

test('with the new UI off, a public page keeps its old layout', function (string $route) {
    config(['platform.ui_v2' => false]);

    $this->get(route($route))->assertOk()->assertDontSee('data-shell=', false);
})->with('guest pages');

test('with the new UI on, a player page opens in the site shell', function (string $route, string $shell) {
    config(['platform.ui_v2' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route($route))->assertOk()
        ->assertSee('data-shell="'.$shell.'"', false)
        ->assertSee('id="site-menu"', false);
})->with('player pages');

test('with the new UI off, a player page keeps its old layout', function (string $route) {
    config(['platform.ui_v2' => false]);

    $this->actingAs(User::factory()->create())
        ->get(route($route))->assertOk()->assertDontSee('data-shell=', false);
})->with('player pages');

test('the referrals page opens in the shell when referrals are on', function () {
    config(['platform.ui_v2' => true, 'kadi.referrals.enabled' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('referrals'))->assertOk()->assertSee('data-shell="app"', false);
});

test('gate pages open in the shell with the menu shown but inert', function () {
    config(['platform.ui_v2' => true]);
    $user = User::factory()->withoutConsent()->create();

    $html = $this->actingAs($user)->get(route('consent.show'))->assertOk()->getContent();

    expect($html)->toContain('data-shell="panel"')
        ->and($html)->toMatch('/<nav id="site-menu"[^>]*\sinert[\s>]/')
        ->and($html)->toMatch('/<nav class="nt-bottom nt-gated"[^>]*\sinert[\s>]/')
        ->and($html)->not->toContain('data-test="wallet-pill"');
});

test('a guest sees Log in and Join, and account links send them to sign in', function () {
    config(['platform.ui_v2' => true]);

    $html = $this->get(route('faq'))->assertOk()->getContent();

    expect($html)->toContain('href="'.route('login').'"')
        ->and($html)->toContain('href="'.route('register').'"')
        ->and($html)->not->toContain('data-test="wallet-pill"')
        ->and($html)->not->toContain('aria-label="Your account"')
        ->and($html)->toMatch('#<a href="'.preg_quote(route('wallet'), '#').'"\s+class="nt-nav-item"[^>]*data-needs-account#');
});

test('a player sees the wallet pill and the account menu, not Log in and Join', function () {
    config(['platform.ui_v2' => true]);

    $html = $this->actingAs(User::factory()->create(['name' => 'Jane Wanjiru']))
        ->get(route('faq'))->assertOk()->getContent();

    expect($html)->toContain('data-test="wallet-pill"')
        ->and($html)->toContain('aria-label="Your account"')
        ->and($html)->toContain('>JW</button>')
        ->and($html)->toContain('href="'.route('profile').'#security"')
        ->and($html)->toContain('action="'.route('logout').'"')
        ->and($html)->not->toContain('data-needs-account')
        ->and($html)->not->toMatch('#<a href="'.preg_quote(route('login'), '#').'"#');
});

test('the side menu marks the current page', function () {
    config(['platform.ui_v2' => true]);

    $html = $this->get(route('faq'))->assertOk()->getContent();

    expect($html)->toMatch('#<a href="'.preg_quote(route('faq'), '#').'"\s+class="nt-nav-item"[^>]*aria-current="page"#')
        ->and($html)->not->toMatch('#<a href="'.preg_quote(route('legal.terms'), '#').'"\s+class="nt-nav-item"[^>]*aria-current="page"#');
});

test('an unverified e-mail shows the e-mail notice before anything else', function () {
    config(['platform.ui_v2' => true]);
    $user = User::factory()->unverified()->phoneUnverified()->create();

    $this->actingAs($user)->get(route('faq'))->assertOk()
        ->assertSee('data-test="notice-email"', false)
        ->assertSee('action="'.route('verification.send').'"', false)
        ->assertDontSee('data-test="notice-phone"', false);
});

test('an unconfirmed phone shows the phone notice on every page', function (string $route) {
    config(['platform.ui_v2' => true]);
    $user = User::factory()->phoneUnverified()->create();

    $this->actingAs($user)->get(route($route))->assertOk()
        ->assertSee('data-test="notice-phone"', false)
        ->assertSee("open-phone-required', { purpose: 'verify' }", false);
})->with(['faq', 'wallet', 'profile']);

test('a verified player sees no notice', function () {
    config(['platform.ui_v2' => true]);

    $this->actingAs(User::factory()->create(['phone' => '254712345678']))->get(route('faq'))->assertOk()
        ->assertDontSee('data-test="notice-email"', false)
        ->assertDontSee('data-test="notice-phone"', false);
});

test('the shell renders the phone sheet, install dialog and bell once', function () {
    config(['platform.ui_v2' => true]);

    $html = $this->actingAs(User::factory()->create())->get(route('wallet'))->assertOk()->getContent();

    expect(substr_count($html, 'data-test="wallet-pill"'))->toBe(1)
        ->and(substr_count($html, 'wire:click="show"'))->toBe(1)
        ->and(substr_count($html, 'Install the app'))->toBeGreaterThanOrEqual(1);
});

test('until sports and the casino page exist, the bottom bar offers Wallet, Kadi, Home and Account', function () {
    config(['platform.ui_v2' => true, 'platform.sports' => false]);

    $labels = collect((new SiteNavigation(null))->bottomBar())->pluck('label')->all();

    expect($labels)->toBe(['Menu', 'Wallet', 'Kadi', 'Home', 'Account']);
});

test('the game tabs list only games that have a page', function () {
    config(['platform.sports' => true]);

    $labels = collect((new SiteNavigation(null))->products())->pluck('label')->all();

    expect($labels)->toBe(['Kadi']);
});

test('a guest gets a sign-in link for Play Kadi; a player gets the real control', function () {
    $guest = collect((new SiteNavigation(null))->groups())->firstWhere('key', 'kadi')['items'][0];
    $player = collect((new SiteNavigation(User::factory()->make()))->groups())->firstWhere('key', 'kadi')['items'][0];

    expect($guest)->toMatchArray(['kind' => 'link', 'href' => route('login'), 'needsAccount' => true])
        ->and($player)->toMatchArray(['kind' => 'play-kadi', 'href' => null]);
});

test('Invite & Earn appears only while referrals are on', function (bool $enabled) {
    config(['kadi.referrals.enabled' => $enabled]);

    $labels = collect((new SiteNavigation(null))->accountLinks())->pluck('label');

    expect($labels->contains('Invite & Earn'))->toBe($enabled);
})->with([true, false]);

test('the search box shows only once games open inside the site, and pages never read the catalogue for it', function (bool $launcher) {
    config(['platform.ui_v2' => true, 'platform.token_launch' => $launcher]);

    $html = $this->get(route('faq'))->assertOk()->getContent();

    expect(str_contains($html, 'role="search"'))->toBe($launcher);
    Http::assertNotSent(fn ($request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/games'));
})->with([true, false]);

test('the search index lists playable titles with their play pages', function () {
    config(['platform.token_launch' => true]);
    $this->games = [[
        'code' => 'slots', 'name' => 'Slots', 'status' => 'active', 'sort_order' => 2,
        'launch_url' => 'https://slots.kadi.online/play',
        'products' => [['code' => 'slots_berry_blast', 'name' => 'Berry Blast', 'status' => 'active']],
    ]];

    $response = $this->getJson(route('games.search'))->assertOk()
        ->assertJsonPath('data.0.name', 'Berry Blast')
        ->assertJsonPath('data.0.url', route('play', ['game' => 'slots', 'title' => 'berry-blast']));

    expect($response->headers->get('Cache-Control'))->toContain('public')->toContain('max-age=300');
});

test('the search index does not exist until games open inside the site', function () {
    config(['platform.token_launch' => false]);

    $this->getJson('/games/search')->assertNotFound();
});

test('on a gate the footer is inert too, and no notice or phone sheet is offered', function () {
    config(['platform.ui_v2' => true]);
    $user = User::factory()->withoutConsent()->unverified()->phoneUnverified()->create();

    $html = $this->actingAs($user)->get(route('consent.show'))->assertOk()->getContent();

    expect($html)->toMatch('/<footer class="nt-footer nt-gated"[^>]*\sinert[\s>]/')
        ->and($html)->not->toContain('data-test="notice-email"')
        ->and($html)->not->toContain('data-test="notice-phone"')
        ->and($html)->not->toContain('aria-label="Open menu"');
});

test('every Play Kadi control is disabled while the e-mail is unverified', function () {
    config(['platform.ui_v2' => true, 'platform.token_launch' => false]);
    $user = User::factory()->unverified()->create(['account_no' => 'KK-UNVERIFIED-1']);

    $html = $this->actingAs($user)->get(route('faq'))->assertOk()->getContent();

    expect($html)->toContain('disabled data-play-kadi')
        ->and($html)->not->toContain('name="ggid"')
        ->and($html)->not->toContain('KK-UNVERIFIED-1');
});

test('the avatar shows the player initials from the model', function () {
    expect(User::factory()->make(['name' => 'jane  wanjiru'])->initials())->toBe('JW');
});

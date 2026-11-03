<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

test('play form posts to the play url', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $playUrl = config('services.kadi_api.play_url');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee($playUrl, false);
});

test('the app account no takes precedence over the cached kadi mirror', function () {
    $user = User::factory()->create(['account_no' => 'app-google-123']);
    Cache::put("kadi.customer.{$user->id}", [
        'balance' => 500,
        'account_no' => 'kadi-mirror-999',
    ], now()->addHour());

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('value="app-google-123"', false)
        ->assertDontSee('kadi-mirror-999', false);
});

test('the cached kadi mirror account no is used as a fallback', function () {
    $user = User::factory()->create(['account_no' => 'kadi-mirror-999']);
    Cache::put("kadi.customer.{$user->id}", [
        'balance' => 500,
        'account_no' => 'kadi-mirror-999',
    ], now()->addHour());

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('value="kadi-mirror-999"', false);
});

test('balance card prefers the fresher wallet balance cache over the stale profile mirror', function () {
    $user = User::factory()->create();

    // Header widget source (fresh, 5-min TTL) vs profile mirror (stale, 1h TTL).
    Cache::put("wallet_balance_{$user->id}", 777.0, now()->addMinutes(5));
    Cache::put("kadi.customer.{$user->id}", ['balance' => 111.0], now()->addHour());

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('KES 777', false);
});

test('with the launcher off, every Play Kadi button is its own ggid form posted to the play url', function () {
    config(['platform.token_launch' => false]);
    $user = User::factory()->create(['account_no' => 'KK-FORM-1']);

    $html = preg_replace('/<!--.*?-->/s', '', $this->actingAs($user)->get(route('dashboard'))->getContent());

    expect($html)->not->toContain('onclick=')
        ->and($html)->not->toContain('kadiPlayForm')
        ->and(preg_match_all('#<form method="POST" class="contents" data-play-kadi\s+action="'.preg_quote(config('services.kadi_api.play_url'), '#').'">\s*<input type="hidden" name="ggid" value="KK-FORM-1">\s*<button type="submit"#', $html))
        ->toBeGreaterThanOrEqual(2);
});

test('with the launcher on, Play Kadi posts through the launcher and never prints the ggid', function () {
    config(['platform.token_launch' => true, 'games.games.kadi.frame' => false]);
    $user = User::factory()->create(['account_no' => 'KK-LAUNCH-1']);

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect($html)->toContain('action="'.route('play.launch', ['game' => 'kadi']).'"')
        ->and($html)->not->toContain('KK-LAUNCH-1"')
        ->and($html)->not->toContain('name="ggid"');
});

test('a disabled Play Kadi is a disabled button, never a live link', function () {
    config(['platform.token_launch' => true, 'games.games.kadi.frame' => true]);
    $this->actingAs(User::factory()->create());

    $html = Blade::render('<x-play-kadi :disabled="true">Play</x-play-kadi>');

    expect($html)->toContain('<button type="button" disabled data-play-kadi')->not->toContain('href=');
});

test('with the launcher on and Kadi framed, Play Kadi links to the in-site play page', function () {
    config(['platform.token_launch' => true, 'games.games.kadi.frame' => true]);

    $html = $this->actingAs(User::factory()->create())->get(route('dashboard'))->getContent();

    expect($html)->toContain('href="'.route('play', ['game' => 'kadi']).'" data-play-kadi');
});

<?php

use App\Models\User;

test('profile page always loads the passkey sdk, even behind the confirmation gate', function () {
    // Regression: x-passkey-registration only renders after password
    // confirmation, so its @assets block was absent on fresh loads and
    // window.Passkeys never materialised — the browser was wrongly
    // reported as unsupported. The SDK must load from PasskeyManager's
    // always-rendered root instead.
    $user = User::factory()->create();

    $html = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('account'))
        ->getContent();

    $devModeLoaded = str_contains($html, 'resources/js/passkeys.js');
    $builtModeLoaded = (bool) preg_match('#/build/assets/passkeys[^"]*\.js#', $html);

    expect($devModeLoaded || $builtModeLoaded)->toBeTrue();
});

test('vite config registers the passkey sdk as a build entry', function () {
    $config = file_get_contents(base_path('vite.config.js'));

    expect($config)->toContain("'resources/js/passkeys.js'");
});

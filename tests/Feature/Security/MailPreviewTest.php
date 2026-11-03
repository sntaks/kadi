<?php

use App\Models\User;

test('mail previews are not found when previews are off', function (string $type) {
    config(['app.mail_previews' => false]);

    $this->get("/email/preview/{$type}")->assertNotFound();
})->with(['verify', 'welcome', 'security']);

test('mail previews use a made-up player, never a real account', function (string $type) {
    config(['app.mail_previews' => true]);
    $real = User::factory()->create(['name' => 'RealPlayer', 'email' => 'real.player@kadi.test']);

    $this->get("/email/preview/{$type}")
        ->assertOk()
        ->assertSee('Preview Player')
        ->assertDontSee($real->name)
        ->assertDontSee($real->email);
})->with(['verify', 'welcome', 'security']);

test('signed-in players do not see their own details in a preview either', function () {
    config(['app.mail_previews' => true]);
    $player = User::factory()->create(['name' => 'SignedIn', 'email' => 'signed.in@kadi.test']);

    $this->actingAs($player)->get('/email/preview/welcome')
        ->assertOk()
        ->assertDontSee('signed.in@kadi.test');
});

test('unknown preview types are not found', function () {
    config(['app.mail_previews' => true]);

    $this->get('/email/preview/nope')->assertNotFound();
});

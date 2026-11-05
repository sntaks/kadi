<?php

use App\Models\User;

/*
 * Phase 12: the old site's pages are gone, but their addresses and route names keep working for bookmarks, e-mails
 * and push links, and carry the query string on.
 */

test('the old pages redirect to their new homes', function (string $from, string $to) {
    $this->actingAs(User::factory()->create())->get($from)->assertRedirect($to);
})->with([
    'dashboard' => ['/dashboard', '/'],
    'dashboard from a push link' => ['/dashboard?source=push', '/?source=push'],
    'profile' => ['/profile', '/account'],
    'game history' => ['/games/history', '/activity'],
    'settings' => ['/settings/security', '/account#security'],
]);

test('the old route names still resolve', function () {
    expect(route('dashboard', absolute: false))->toBe('/dashboard')
        ->and(route('profile', absolute: false))->toBe('/profile')
        ->and(route('games.history', absolute: false))->toBe('/games/history');
});

test('a guest following an old link lands on the page that asks them to sign in', function () {
    $this->get('/profile')->assertRedirect('/account');
    $this->get('/account')->assertRedirect(route('login'));
});

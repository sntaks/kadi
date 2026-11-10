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

/*
 * Production caches its routes (`route:cache`), which serialises every closure action. A closure returned by another
 * closure lost its argument there (Sentry KADI-F: /dashboard was a 500), so this runs the same serialisation first.
 */
test('the old pages still redirect once the routes are cached', function (string $from, string $to) {
    foreach (app('router')->getRoutes() as $route) {
        $route->prepareForSerialization();
    }

    $this->actingAs(User::factory()->create())->get($from)->assertRedirect($to);
})->with([
    'dashboard' => ['/dashboard?source=push', '/?source=push'],
    'profile' => ['/profile', '/account'],
    'game history' => ['/games/history', '/activity'],
    'settings' => ['/settings', '/account#profile'],
    'settings security' => ['/settings/security', '/account#security'],
]);

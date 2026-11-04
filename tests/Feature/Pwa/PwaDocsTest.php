<?php

use App\Notifications\PushMessageNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/**
 * docs/pwa-push.md makes concrete claims (files, commands, routes, config, env vars).
 * These tests fail when one of them stops being true, so the doc cannot silently rot.
 *
 * Assertions are written as booleans with a message on purpose: `toContain()` on the whole
 * document would print all of it when it fails and bury the one thing that is wrong.
 */
function pwaDoc(): string
{
    return str_replace("\r\n", "\n", file_get_contents(base_path('docs/pwa-push.md')));
}

function docMentions(string $needle): bool
{
    return str_contains(pwaDoc(), $needle);
}

test('every repository path the doc points at exists', function () {
    preg_match_all('/`((?:app|resources|public|database|tests|routes|config)\/[A-Za-z0-9_\-.\/]*)`/', pwaDoc(), $matches);

    $missing = collect($matches[1])
        ->unique()
        ->reject(fn (string $path) => file_exists(base_path($path)))
        ->values()
        ->all();

    expect($matches[1])->not->toBeEmpty()->and($missing)->toBe([]);
});

test('the files listed in the file map exist', function () {
    // The map groups files under a directory heading and often lists them by name, so the doc must
    // name each file (by its file name) and the file must exist at its full path.
    foreach ([
        'public/sw.js', 'app/Support/WebManifest.php', 'public/offline.html', 'public/pwa-icons/badge-72.png',
        'resources/js/pwa/install.js', 'resources/js/pwa/push.js', 'resources/js/pwa/register-sw.js',
        'resources/views/components/pwa/install-button.blade.php',
        'resources/views/components/pwa/install-dialog.blade.php',
        'resources/views/components/pwa/notification-toggle.blade.php',
        'app/Notifications/PushMessageNotification.php', 'app/Services/PushTestSender.php',
        'app/Support/PushEndpoint.php', 'app/Listeners/LogPushFailure.php', 'app/Console/Commands/PushTest.php',
    ] as $path) {
        expect(docMentions(basename($path)))->toBeTrue('the doc no longer names '.basename($path));
        expect(file_exists(base_path($path)))->toBeTrue("{$path} is in the doc but missing");
    }
});

test('the commands the doc tells people to run exist', function () {
    $commands = array_keys(Artisan::all());

    foreach (['push:test', 'webpush:vapid', 'queue:restart', 'queue:work'] as $command) {
        expect(docMentions($command))->toBeTrue("the doc no longer mentions `{$command}`");
        expect(in_array($command, $commands, true))->toBeTrue("`{$command}` is in the doc but is not a registered command");
    }
});

test('the routes, config keys and env vars the doc names exist', function () {
    foreach (['push.subscriptions.store', 'push.subscriptions.destroy', 'push.test'] as $route) {
        expect(Route::has($route))->toBeTrue("route {$route}");
    }

    expect(config('kadi.push.test_environments'))->toBeArray()->not->toBeEmpty()
        ->and(config('kadi.push.allowed_endpoint_hosts'))->toBeArray()->not->toBeEmpty();

    foreach (['kadi.push.test_environments', 'allowed_endpoint_hosts'] as $key) {
        expect(docMentions($key))->toBeTrue("the doc no longer mentions {$key}");
    }

    $example = file_get_contents(base_path('.env.example'));

    foreach (['VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY', 'VAPID_SUBJECT'] as $variable) {
        expect(docMentions($variable))->toBeTrue("the doc no longer mentions {$variable}");
        expect(str_contains($example, $variable.'='))->toBeTrue("{$variable} is missing from .env.example");
    }

    // The example file must never carry real keys.
    expect($example)->toMatch('/^VAPID_PUBLIC_KEY=$/m')->toMatch('/^VAPID_PRIVATE_KEY=$/m');
});

test('the doc and the code agree on the numbers people rely on', function () {
    // rate limits, logout cap, retry backoff, and the nested queue limits production relies on
    foreach (['20/min', '5/min', '1.5s', '10s then 60s', 'push job timeout (60s)', 'REDIS_QUEUE_RETRY_AFTER=120', 'queue:work redis'] as $claim) {
        expect(docMentions($claim))->toBeTrue("the doc no longer states `{$claim}`");
    }

    expect(file_get_contents(base_path('resources/js/pwa/push.js')))->toContain('LOGOUT_TIMEOUT_MS = 1500');
    expect(file_get_contents(base_path('app/Providers/AppServiceProvider.php')))
        ->toContain("RateLimiter::for('push', fn (Request \$request) => Limit::perMinute(20)")
        ->toContain("RateLimiter::for('push-test', fn (Request \$request) => Limit::perMinute(5)");

    $notification = new PushMessageNotification('t', 'b');
    expect($notification->tries)->toBe(3)->and($notification->backoff)->toBe([10, 60])->and($notification->timeout)->toBe(60);

    // The env var the doc tells production to set must really be the one the redis connection reads.
    expect(file_get_contents(base_path('config/queue.php')))->toContain("env('REDIS_QUEUE_RETRY_AFTER'");
});

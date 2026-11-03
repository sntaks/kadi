<?php

/**
 * Loads a config file with the given variables unset, so the test checks the
 * shipped defaults rather than whatever the developer's .env turns on.
 *
 * @param  list<string>  $keys
 */
function configDefaults(string $file, array $keys): array
{
    $saved = [];
    foreach ($keys as $key) {
        $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    try {
        return require dirname(__DIR__, 2).'/config/'.$file;
    } finally {
        foreach ($saved as $key => [$env, $server, $getenv]) {
            if ($env !== null) {
                $_ENV[$key] = $env;
            }
            if ($server !== null) {
                $_SERVER[$key] = $server;
            }
            if ($getenv !== false) {
                putenv("{$key}={$getenv}");
            }
        }
    }
}

test('every multi-game platform switch exists and is off by default', function () {
    $flags = configDefaults('platform.php', [
        'PLATFORM_UI_V2', 'PLATFORM_TOKEN_LAUNCH', 'PLATFORM_DEEP_LINK', 'PLATFORM_SPORTS', 'PLATFORM_SPORTS_BETTING',
    ]);

    expect(array_keys($flags))->toBe(['ui_v2', 'token_launch', 'deep_link', 'sports', 'sports_betting'])
        ->and(array_filter($flags))->toBe([]);
});

test('mail previews default to on only in the local environment', function (string $env, bool $expected) {
    $previous = [$_ENV['APP_ENV'] ?? null, $_SERVER['APP_ENV'] ?? null, getenv('APP_ENV')];
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = $env;
    putenv("APP_ENV={$env}");

    try {
        expect(configDefaults('app.php', ['MAIL_PREVIEWS'])['mail_previews'])->toBe($expected);
    } finally {
        [$_ENV['APP_ENV'], $_SERVER['APP_ENV']] = $previous;
        $previous[2] === false ? putenv('APP_ENV') : putenv("APP_ENV={$previous[2]}");
    }
})->with([
    'local' => ['local', true],
    'testing' => ['testing', false],
    'staging' => ['staging', false],
    'production' => ['production', false],
]);

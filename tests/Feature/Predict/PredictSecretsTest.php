<?php

use Illuminate\Support\Facades\File;

/**
 * The Kadi API site key is a server secret: it must never reach the browser. Pages are checked in
 * PredictionPagesTest; this covers the built front end and the variables Vite would bundle.
 */
test('the site key is not in any built front-end file', function () {
    $key = (string) env('API_KEY', '');

    if (strlen($key) < 8 || ! File::isDirectory(public_path('build'))) {
        $this->markTestSkipped('No real site key or no built assets here (CI builds them elsewhere).');
    }

    $leaks = collect(File::allFiles(public_path('build')))
        ->filter(fn ($file) => str_contains($file->getContents(), $key))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($leaks)->toBe([]);
});

test('no VITE_ variable carries a server key: Vite bundles every VITE_ value into the page', function () {
    $names = collect(file(base_path('.env.example'), FILE_IGNORE_NEW_LINES))
        ->map(fn (string $line) => trim(explode('=', $line, 2)[0]))
        ->filter(fn (string $name) => str_starts_with($name, 'VITE_'));

    // Reverb's app key is public by design (it only names the app); anything else with KEY or SECRET is not.
    $secrets = $names->filter(fn (string $name) => preg_match('/KEY|SECRET|TOKEN/', $name) && $name !== 'VITE_REVERB_APP_KEY')->values()->all();

    expect($secrets)->toBe([]);
});

test('the browser code never reads the API key or calls the Kadi API itself', function () {
    $offenders = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($file) => preg_match('/API_KEY|x-api-key|\/api\/v1\/predict/i', $file->getContents()))
        ->map(fn ($file) => $file->getRelativePathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

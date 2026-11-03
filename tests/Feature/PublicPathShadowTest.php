<?php

use Illuminate\Support\Facades\Route;

/*
 * The web server serves anything that exists under public/ before Laravel sees the request
 * (public/.htaccess, nginx try_files). A folder named like a page turns that page into a 403.
 */
test('no file or folder under public/ has the same path as a page', function () {
    $shadowed = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array('GET', $route->methods(), true))
        ->map(fn ($route) => preg_replace('#/?\{[^}]+\?\}#', '', $route->uri())) // optional segments may be left out
        ->reject(fn (string $uri) => $uri === '/' || str_contains($uri, '{'))
        ->unique()
        ->filter(fn (string $uri) => file_exists(public_path($uri)) && $uri !== 'sitemap.xml')
        ->values()
        ->all();

    expect($shadowed)->toBe([]);
});

<?php

use Illuminate\Support\Facades\File;

test('the sitemap lists only public pages that exist', function () {
    config(['platform.deep_link' => false]); // the game pages come from the catalogue: covered in LobbyTest
    $realPublic = public_path();
    $public = storage_path('framework/testing/sitemap-'.uniqid());
    File::ensureDirectoryExists($public);
    app()->usePublicPath($public);

    $this->artisan('sitemap:generate')->assertSuccessful()->run();
    $xml = File::get($public.'/sitemap.xml');

    app()->usePublicPath($realPublic);
    File::deleteDirectory($public);

    preg_match_all('#<loc>([^<]+)</loc>#', $xml, $matches);
    $paths = array_map(fn (string $url) => parse_url($url, PHP_URL_PATH) ?? '/', $matches[1]);

    expect($paths)->toBe(['/', '/how-to', '/games-guide', '/faq', '/terms', '/privacy', '/promotions']);

    foreach ($paths as $path) {
        $this->get($path)->assertOk();
    }
});

test('the promotions page is listed', function () {
    $realPublic = public_path();
    $public = storage_path('framework/testing/sitemap-'.uniqid());
    File::ensureDirectoryExists($public);
    app()->usePublicPath($public);

    $this->artisan('sitemap:generate')->assertSuccessful()->run();
    $xml = File::get($public.'/sitemap.xml');

    app()->usePublicPath($realPublic);
    File::deleteDirectory($public);

    expect($xml)->toContain(route('promotions'));
});

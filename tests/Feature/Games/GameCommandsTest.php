<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.kadi_api.url' => 'https://kadi-api.test/api/v1/']);

    $this->realPublic = public_path();
    $this->public = storage_path('framework/testing/artwork-'.uniqid());
    File::ensureDirectoryExists($this->public.'/art');
    app()->usePublicPath($this->public);

    config([
        'games.games' => [
            'slots' => ['titles' => true, 'artwork' => 'art/{code}.png'],
            'scratch' => ['titles' => true, 'artwork_map' => ['scratch_one' => 'art/one.png']],
        ],
        'games.collections' => ['fruit' => ['label' => 'Fruit', 'products' => ['alpha', 'missing']]],
        'games.artwork' => ['thumbnails' => 'thumbs', 'width' => 32, 'height' => 48, 'quality' => 70],
    ]);

    foreach (['alpha', 'one'] as $name) {
        $image = imagecreatetruecolor(100, 150);
        imagepng($image, $this->public."/art/{$name}.png");
    }
});

afterEach(function () {
    app()->usePublicPath($this->realPublic);
    File::deleteDirectory($this->public);
});

test('games:artwork builds small WebP thumbnails and reports titles without artwork', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    $this->artisan('games:artwork')
        ->expectsOutputToContain('Built 2')
        ->expectsOutputToContain('No usable artwork for: missing')
        ->assertFailed();

    Http::assertNothingSent(); // config only: the build never depends on the network

    $thumb = $this->public.'/thumbs/alpha.webp';
    expect($thumb)->toBeFile()
        ->and(getimagesize($thumb))->toMatchArray([0 => 32, 1 => 48, 'mime' => 'image/webp'])
        ->and($this->public.'/thumbs/scratch_one.webp')->toBeFile();
});

test('games:artwork skips thumbnails that are up to date unless forced', function () {
    Http::fake(['*' => Http::response(['data' => []])]);

    $this->artisan('games:artwork', ['--only' => ['alpha']])->expectsOutputToContain('Built 1')->assertSuccessful();
    $this->artisan('games:artwork', ['--only' => ['alpha']])->expectsOutputToContain('Built 0, up to date 1')->assertSuccessful();
    $this->artisan('games:artwork', ['--only' => ['alpha'], '--force' => true])->expectsOutputToContain('Built 1')->assertSuccessful();
});

test('games:artwork --with-api also builds titles only the API lists', function () {
    imagepng(imagecreatetruecolor(100, 150), $this->public.'/art/beta.png');
    Http::fake(['*/games' => Http::response(['data' => [
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            ['code' => 'beta', 'name' => 'Beta', 'status' => 'active'],
        ]],
    ]])]);

    $this->artisan('games:artwork', ['--with-api' => true, '--only' => ['beta']])->expectsOutputToContain('Built 1')->assertSuccessful();

    expect($this->public.'/thumbs/beta.webp')->toBeFile();
});

test('games:list shows the joined lobby and flags gaps on both sides', function () {
    Http::fake(['*/games' => Http::response(['data' => [
        ['code' => 'slots', 'name' => 'Slots', 'launch_url' => 'https://slots.kadi.online', 'status' => 'active', 'sort_order' => 2, 'products' => [
            ['code' => 'alpha', 'name' => 'Alpha', 'status' => 'active'],
            ['code' => 'unknown', 'name' => 'Unknown', 'status' => 'active'],
        ]],
    ]])]);

    $this->artisan('games:list')
        ->expectsOutputToContain('slots Slots · https://slots.kadi.online · 2 products')
        ->expectsOutputToContain('Shown as fallback tiles (not in config/games.php): unknown')
        ->expectsOutputToContain('In config/games.php but not offered by the API (hidden): missing, scratch_one')
        ->assertSuccessful();
});

test('games:list fails clearly when the catalogue is empty', function () {
    Http::fake(['*' => Http::failedConnection()]);

    $this->artisan('games:list')->expectsOutputToContain('The catalogue is empty')->assertFailed();
});

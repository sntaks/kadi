<?php

/*
 * Phase 11: the lobby, sports, slots, scratch cards and promotions each have their own share image.
 */

test('every share image exists at the size link previews expect', function (string $file) {
    $path = public_path("images/{$file}");

    expect(file_exists($path))->toBeTrue("{$file} is missing")
        ->and(array_slice(getimagesize($path), 0, 2))->toBe([1200, 630])
        ->and(filesize($path))->toBeLessThan(400_000);
})->with(['og-lobby.jpg', 'og-sports.jpg', 'og-slots.jpg', 'og-scratch.jpg', 'og-promotions.jpg']);

test('the sports pages share the sports image', function () {
    config(['platform.ui_v2' => true, 'platform.sports' => true]);

    $this->get('/sports')->assertOk()
        ->assertSee('<meta property="og:image"        content="'.url('/images/og-sports.jpg').'">', false);
});

test('pages without their own image keep the default', function () {
    $this->get('/faq')->assertOk()->assertSee(url('/images/og-default.png'), false);
});

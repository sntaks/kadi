<?php

use Illuminate\Support\Facades\Http;

/*
 * The BCLB licence number (KADI_LICENCE_NUMBER) is shown in the footer of every page, the landing included,
 * and hidden while it is blank.
 */
beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response([], 200));
});

test('the landing and other pages show the BCLB licence number in the footer', function (string $path) {
    config(['kadi.legal.licence_number' => 'BK-0000123']);

    $this->get($path)->assertOk()
        ->assertSee('data-test="bclb-licence"', false)
        ->assertSee('Betting Control and Licensing Board (BCLB)')
        ->assertSee('BK-0000123');
})->with(['/', '/faq']);

test('no licence line is shown while the number is blank', function (?string $number) {
    config(['kadi.legal.licence_number' => $number]);

    $this->get('/')->assertOk()->assertDontSee('data-test="bclb-licence"', false);
})->with([null, '', '  ']);

<?php

use App\Models\User;

test('signed-in players get the install entry in the account menu and drawer, and the instructions dialog once', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->getContent();

    expect($html)->toContain('data-test="pwa-install-shell"')
        ->toContain('x-on:pwa-install-help.window')
        ->toContain('aria-live="polite"')
        ->and(substr_count($html, 'id="pwa-install-dialog"'))->toBe(1);
});

test('the wallet page has the install button in its title row, away from the money controls', function () {
    $html = $this->actingAs(User::factory()->create())->get(route('wallet'))->assertOk()->getContent();

    expect($html)->toContain('data-test="pwa-install-chip"')->toContain('id="pwa-install-dialog"');
    expect(strpos($html, 'data-test="pwa-install-chip"'))->toBeLessThan(strpos($html, 'wire:submit'));
});

test('every install entry point opts out of livewire morphing', function () {
    $html = view('components.pwa.install-button', ['variant' => 'chip'])->render()
        .view('components.pwa.install-button', ['variant' => 'shell'])->render();

    expect(substr_count($html, 'wire:ignore'))->toBe(2);
});

test('guests never see any install UI', function (string $route) {
    $html = $this->get(route($route))->assertOk()->getContent();

    expect($html)->not->toContain('pwa-install-shell')
        ->not->toContain('pwa-install-chip')
        ->not->toContain('pwa-install-dialog');
})->with(['login', 'register', 'legal.terms', 'home']);

test('the install module is wired into the app bundle', function () {
    $app = file_get_contents(resource_path('js/app.js'));
    $install = file_get_contents(resource_path('js/pwa/install.js'));

    expect($app)->toContain("from './pwa/install.js'")->toContain('initInstall()');

    // Behaviour the store must keep: capture the event, hide when installed or on Firefox desktop.
    expect($install)->toContain("addEventListener('beforeinstallprompt'")
        ->toContain("addEventListener('appinstalled'")
        ->toContain('isFirefoxDesktop')
        ->toContain('display-mode: standalone')
        ->toContain('maxTouchPoints'); // iPadOS reports as a Mac
});

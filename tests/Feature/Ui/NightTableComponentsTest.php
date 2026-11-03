<?php

use App\Games\GameTitle;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

function ui(string $blade, array $data = []): string
{
    return preg_replace('/\s+/', ' ', Blade::render($blade, $data));
}

// ─── /_ui gallery ───────────────────────────────────────────────────────────

test('the UI gallery is not found while it is off', function () {
    config(['app.ui_gallery' => false]);

    $this->get('/_ui')->assertNotFound();
});

test('the UI gallery shows every component without the Kadi API', function () {
    config(['app.ui_gallery' => true]);

    $this->get('/_ui')->assertOk()
        ->assertSee('data-ui-gallery', false)
        ->assertSee('class="nt-odds"', false)
        ->assertSee('nt-tile', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('nt-hand', false)
        ->assertSee('noindex', false);
});

// ─── Odds button ────────────────────────────────────────────────────────────

test('an odds button names the pick and price for screen readers', function () {
    $html = ui('<x-ui.odds-button label="1" price="2.6" pick="Arsenal to win" />');

    expect($html)->toContain('aria-pressed="false"')
        ->toContain('aria-label="Arsenal to win at 2.60"')
        ->toContain('2.60')
        ->not->toContain('disabled');
});

test('a selected odds button is pressed and says it is in the slip', function () {
    expect(ui('<x-ui.odds-button label="X" price="3.4" pick="Draw" selected />'))
        ->toContain('aria-pressed="true"')
        ->toContain('Draw at 3.40, in your bet slip');
});

test('a moved price shows its direction, in text too', function (string $movement, string $arrow, string $said) {
    expect(ui('<x-ui.odds-button label="2" price="1.9" pick="Inter to win" :movement="$m" />', ['m' => $movement]))
        ->toContain($arrow)
        ->toContain($said);
})->with([
    'up' => ['up', 'nt-move-up', 'price up'],
    'down' => ['down', 'nt-move-down', 'price down'],
]);

test('a suspended price is disabled, never pressed, and shows no number', function () {
    $html = ui('<x-ui.odds-button label="1" price="2.6" pick="Arsenal to win" selected suspended />');

    expect($html)->toContain('disabled')
        ->toContain('aria-pressed="false"')
        ->toContain('aria-label="Arsenal to win: suspended"')
        ->not->toContain('2.60');
});

// ─── Tiles ──────────────────────────────────────────────────────────────────

test('a game tile takes its name, art, collection and badge from a catalogue title', function () {
    $title = new GameTitle('slots_triple_jackpot_fruits', 'slots', 'Triple Jackpot Fruits', '/games/thumbs/slots_triple_jackpot_fruits.webp', 'fruit', ['jackpot'], false, true);

    $html = ui('<x-ui.game-tile :title="$t" href="/play/slots/triple-jackpot-fruits" :favourite="true" />', ['t' => $title]);

    expect($html)->toContain('Triple Jackpot Fruits')
        ->toContain('src="/games/thumbs/slots_triple_jackpot_fruits.webp"')
        ->toContain('Fruit stand')
        ->toContain('Jackpot')
        ->toContain('aria-label="Play Triple Jackpot Fruits"')
        ->toContain('aria-pressed="true" aria-label="Favourite Triple Jackpot Fruits" data-fav="slots_triple_jackpot_fruits"');
});

test('a tile without artwork still shows its name, and no heart unless asked', function () {
    $html = ui('<x-ui.game-tile name="Brand New Title" href="#" />');

    expect($html)->toContain('nt-art-fallback')->toContain('Brand New Title')->not->toContain('nt-fav')->not->toContain('<img');
});

// ─── Tabs, chips, buttons ───────────────────────────────────────────────────

test('in-page tabs are role=tab with aria-selected; link tabs use aria-current', function () {
    $tabs = ui('<x-ui.tabs label="When"><x-ui.tab active>Today</x-ui.tab><x-ui.tab>Tomorrow</x-ui.tab></x-ui.tabs>');
    $nav = ui('<x-ui.tabs label="Account" as="nav"><x-ui.tab href="/wallet" active>Wallet</x-ui.tab></x-ui.tabs>');

    expect($tabs)->toContain('role="tablist" aria-label="When"')
        ->toContain('role="tab" aria-selected="true" tabindex="0"')
        ->toContain('role="tab" aria-selected="false" tabindex="-1"')
        ->and($nav)->toContain('<nav aria-label="Account"')
        ->toContain('href="/wallet" aria-current="page"');
});

test('chips are toggles and buttons render as links only with an href', function () {
    expect(ui('<x-ui.chip pressed>Featured</x-ui.chip>'))->toContain('aria-pressed="true"')
        ->and(ui('<x-ui.button href="/wallet">Wallet</x-ui.button>'))->toContain('<a href="/wallet"')->toContain('nt-btn nt-btn-gold')
        ->and(ui('<x-ui.button variant="ghost" size="sm" type="submit">Go</x-ui.button>'))->toContain('<button type="submit"')->toContain('nt-btn nt-btn-ghost nt-btn-sm')
        ->and(ui('<x-ui.icon-button label="Close">x</x-ui.icon-button>'))->toContain('aria-label="Close"');
});

// ─── Sheet, slip pill, notice ───────────────────────────────────────────────

test('a sheet is a labelled modal dialog that traps focus while open', function () {
    $html = ui('<x-ui.sheet name="deposit" title="Deposit with M-Pesa">Body</x-ui.sheet>');

    expect($html)->toContain('role="dialog" aria-modal="true" aria-labelledby="sheet-deposit-title"')
        ->toContain('id="sheet-deposit-title">Deposit with M-Pesa</h2>')
        ->toContain('x-trap.inert.noscroll="open"')
        ->toContain('aria-label="Close"');
});

test('the slip pill says how many selections and the possible payout', function () {
    expect(ui('<x-ui.slip-pill :count="1" payout="KES 260" />'))->toContain('aria-label="Bet slip, 1 selection, possible payout KES 260"')
        ->and(ui('<x-ui.slip-pill :count="3" />'))->toContain('aria-label="Bet slip, 3 selections"');
});

test('the notice is announced politely and takes an action', function () {
    expect(ui('<x-ui.notice>Confirm your phone<x-slot:action><a href="#">Confirm</a></x-slot:action></x-ui.notice>'))
        ->toContain('role="status"')->toContain('Confirm your phone')->toContain('>Confirm</a>');
});

test('the Kadi hand is decorative', function () {
    expect(ui('<x-ui.kadi-hand />'))->toContain('aria-hidden="true" class="nt-hand"')
        ->and(substr_count(ui('<x-ui.kadi-hand />'), 'class="nt-card'))->toBe(4);
});

test('an odds button without a price yet cannot be pressed', function () {
    $html = ui('<x-ui.odds-button label="X" pick="Draw" />');

    expect($html)->toContain('disabled')
        ->toContain('aria-label="Draw: no price yet"')
        ->not->toContain('Draw at');
});

test('a disabled link button has no href and is announced as disabled', function () {
    $html = ui('<x-ui.button href="/wallet" disabled>Deposit</x-ui.button>');

    expect($html)->toContain('aria-disabled="true"')->not->toContain('href=');
});

test('in-page tabs move with the arrow keys, Home and End', function () {
    $html = ui('<x-ui.tabs label="When"><x-ui.tab active>Today</x-ui.tab><x-ui.tab>Tomorrow</x-ui.tab></x-ui.tabs>');

    expect($html)->toContain('x-on:keydown.right.prevent="move(1)"')
        ->toContain('x-on:keydown.left.prevent="move(-1)"')
        ->toContain('x-on:keydown.home.prevent')
        ->toContain('x-on:keydown.end.prevent');
});

test('sheet motion composes with centring instead of replacing it', function () {
    $html = ui('<x-ui.sheet name="d" title="Deposit" center>Body</x-ui.sheet>');

    expect($html)->toContain('x-transition:enter-start="nt-sheet-from"')->not->toContain('translate-y-');
});

test('the gallery builds its tiles exactly like the lobby, fallback tile included', function () {
    config(['app.ui_gallery' => true]);

    $titles = $this->get('/_ui')->assertOk()->viewData('titles');

    expect($titles->firstWhere('code', 'slots_triple_jackpot_fruits')->badges)->toBe(['jackpot'])
        ->and($titles->firstWhere('code', 'slots_dragon_fire_fortune')->featured)->toBeTrue()
        ->and($titles->firstWhere('code', 'slots_brand_new')->isPresented())->toBeFalse();
});

test('Night table tokens never reuse a class name existing pages already use', function () {
    $css = file_get_contents(resource_path('css/night-table.css'));
    preg_match_all('/--color-([a-z0-9-]+):/', $css, $m);

    $views = collect(File::allFiles(resource_path('views')))
        // Night table's own views (components, the site shell, the gallery) are meant to use the tokens.
        ->reject(fn ($f) => Str::contains($f->getPathname(), ['components/ui/', 'components/site/', 'layouts/site.blade.php', 'dev/ui-gallery']))
        ->map(fn ($f) => $f->getContents())->implode("\n");

    foreach ($m[1] as $token) {
        expect(preg_match('/\b(bg|text|border|ring|fill|stroke|from|to|via|outline|divide)-'.preg_quote($token, '/').'\b/', $views))
            ->toBe(0, "The token `{$token}` already means something on an existing page.");
    }
});

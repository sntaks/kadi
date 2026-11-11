<?php

use App\Support\SiteNavigation;

/*
 * The side menu's sizes (resources/js/site/side-rail.js): full on desktop and collapsible to an icon rail, an icon rail
 * on tablets, a drawer on phones. These check the markup the script and the CSS rely on.
 */
test('the shell runs the side-menu sizes and sets the rail before the first paint', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('x-data="siteShell"')
        ->toContain('x-on:resize.window.debounce.150ms="resized()"')
        ->toContain("localStorage.getItem('kadi.side.rail')")
        ->toContain('w>=768&&(w<1240||');
});

test('desktop offers a collapse button that says what it does', function () {
    $this->get('/')->assertSee('data-test="rail-toggle"', false)
        ->assertSee('aria-label="Collapse menu"', false)
        ->assertSee('x-on:click="toggleRail()"', false);
});

test('on the rail every entry keeps its name for screen readers and a tooltip', function () {
    $html = $this->get('/')->getContent();

    // Group labels are wrapped so the rail can hide them visually without dropping them.
    expect($html)->toMatch('#<span class="nt-label">Kadi</span>#')
        ->toMatch('#<span class="nt-label">Casino</span>#')
        ->and($html)->toContain("x-bind:title=\"railed && ! menuOpen ? 'FAQ' : null\"");
});

test('a rail group opens the full menu instead of a link list that has no room', function () {
    $this->get('/')->assertSee('if (railed && ! menuOpen) { open = true; expand() } else { open = ! open }', false);
});

test('the game groups use the Kadi card, the poker chip and the football', function () {
    $icons = file_get_contents(resource_path('views/components/site/icon.blade.php'));

    expect($icons)->toContain("'kadi' => ")->toContain("'chip' => ")->toContain("'football' => ");

    $groups = collect(app(SiteNavigation::class)->groups())->pluck('icon', 'key')->all();
    expect($groups)->toMatchArray(['kadi' => 'kadi', 'casino' => 'chip', 'sports' => 'football']);
});

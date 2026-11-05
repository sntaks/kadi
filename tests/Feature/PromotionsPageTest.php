<?php

use App\Models\User;
use App\Sportsbook\Models\FreeBet;

/*
 * Phase 11: /promotions brings every offer together. Only offers that run are shown, and every figure comes from
 * config or the player's own free bets: nothing invented.
 */

test('guests see the welcome bonus and Invite & Earn, with ways to join, and no sports offers while sports are off', function () {
    config(['kadi.promotions.signup_bonus_amount' => 20, 'platform.sports' => false]);

    $this->get('/promotions')->assertOk()
        ->assertSee('data-test="offer-welcome"', false)->assertSee('KES 20')
        ->assertSee('data-test="offer-invite"', false)->assertSee('data-needs-account', false)
        ->assertSee('data-signin="join"', false)
        ->assertDontSee('data-test="offer-multibet"', false)
        ->assertDontSee('data-test="offer-free-bets"', false)
        ->assertSee(url('/images/og-promotions.jpg'), false);
});

test('switched-off offers are not shown', function () {
    config(['kadi.promotions.enabled' => false, 'kadi.referrals.enabled' => false, 'platform.sports' => false]);

    $this->get('/promotions')->assertOk()
        ->assertDontSee('data-test="offer-welcome"', false)
        ->assertDontSee('data-test="offer-invite"', false)
        ->assertSee('No offers running right now');
});

test('with sports on, the multibet bonus shows its real tiers', function () {
    config([
        'platform.sports' => true,
        'platform.sports_betting' => true,
        'sportsbook.promotions.multibet_bonus.tiers' => [3 => 3, 4 => 5, 6 => 10],
        'sportsbook.promotions.multibet_bonus.min_price' => '1.30',
    ]);

    $html = $this->get('/promotions')->assertOk()->assertSee('data-test="offer-multibet"', false)->getContent();

    expect($html)->toContain('Up to <span class="nt-num">10%</span>')
        ->toContain('priced 1.30 or more')
        ->toMatch('#<td class="nt-num">3</td>\s*<td class="nt-num">3%</td>#')
        ->toMatch('#<td class="nt-num">4–5</td>\s*<td class="nt-num">5%</td>#')
        ->toMatch('#<td class="nt-num">6\+</td>\s*<td class="nt-num">10%</td>#');
});

test('a player sees their own usable free bets, and nobody else\'s', function () {
    config(['platform.sports' => true, 'platform.sports_betting' => true]);
    $player = User::factory()->create();
    FreeBet::factory()->for($player)->create(['amount' => 5_000, 'min_odds' => '1.50']);
    FreeBet::factory()->create(['amount' => 7_700]);

    $this->actingAs($player)->get('/promotions')->assertOk()
        ->assertSee('data-test="my-free-bets"', false)
        ->assertSee('You have 1 free bet')
        ->assertSee('KES 50.00')
        ->assertDontSee('KES 77.00');
});

test('the menu links to the page', function () {
    $this->get('/promotions')->assertOk()->assertSee('href="'.route('promotions').'"', false);
});

test('sports offers wait until bets can be placed', function () {
    config(['platform.sports' => true, 'platform.sports_betting' => false]);

    $this->get('/promotions')->assertOk()
        ->assertDontSee('data-test="offer-multibet"', false)
        ->assertDontSee('data-test="offer-free-bets"', false);
});

test('with no multibet tiers the bonus card is hidden, never shown empty', function () {
    config(['platform.sports' => true, 'platform.sports_betting' => true, 'sportsbook.promotions.multibet_bonus.tiers' => []]);

    $this->get('/promotions')->assertOk()
        ->assertDontSee('data-test="offer-multibet"', false)
        ->assertSee('data-test="offer-free-bets"', false);
});

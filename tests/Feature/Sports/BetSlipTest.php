<?php

use App\Account\ResponsiblePlay;
use App\Livewire\Account\Activity;
use App\Livewire\Sports\BetSlip;
use App\Livewire\Sports\JackpotPage;
use App\Models\User;
use App\Sportsbook\Enums\BetLegStatus;
use App\Sportsbook\Enums\BetStatus;
use App\Sportsbook\Enums\JackpotEntryStatus;
use App\Sportsbook\Jobs\ReconcileWager;
use App\Sportsbook\Models\Bet;
use App\Sportsbook\Models\BetLeg;
use App\Sportsbook\Models\BookingCode;
use App\Sportsbook\Models\FreeBet;
use App\Sportsbook\Models\JackpotEntry;
use App\Sportsbook\Support\BetOutcome;
use App\Sportsbook\Wagers\WagerPlacement;
use App\Support\SiteNavigation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

/*
 * Phase 9: the bet slip and the jackpot page, paid from the Kadi wallet through the fake wager API.
 */

beforeEach(function () {
    config(['platform.sports' => true]);
    $this->api = wagerApi();
});

/** Whether the Place bet button carries the disabled attribute (not wire:loading's own). */
function placeButtonDisabled(string $html): bool
{
    preg_match('/<button[^>]*data-test="place-bet"[^>]*>/', $html, $tag);

    return (bool) preg_match('/\sdisabled(?=[\s>=])/', str_replace('wire:loading.attr="disabled"', '', $tag[0] ?? ''));
}

function slipFor(?User $player, array $picks, string $stake = '100')
{
    $test = $player ? Livewire::actingAs($player)->test(BetSlip::class) : Livewire::test(BetSlip::class);

    return $test->call('syncPicks', $picks)->set('stake', $stake);
}

test('the slip prices picks on the server, whatever the browser says', function () {
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    slipFor($this->api->player(), [slipPick($home, '9.99')])
        ->assertSee($home->name)
        ->assertSee('Some prices changed')
        ->assertSee('KES 210.00');
});

test('a guest can build a slip and is asked to log in to bet', function () {
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    slipFor(null, [slipPick($home)])->assertSee('Log in to bet')->assertSee('data-signin', false)->assertDontSee('data-test="place-bet"', false);
});

test('placing takes the stake from the Kadi wallet and shows the bet', function () {
    $player = $this->api->player(100_000);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    slipFor($player, [slipPick($home)])
        ->call('placeBet')
        ->assertHasNoErrors()
        ->assertSee('Bet placed')
        ->assertDispatched('betslip-clear')
        ->assertDispatched('wallet-refreshed')
        // Analytics: the kind of bet only, never the stake, payout or reference.
        ->assertDispatched('kadi-track', name: 'bet_placed', params: ['product' => 'sportsbook', 'bet_type' => 'single', 'legs' => 1, 'free_bet' => false, 'in_play' => false]);

    expect(Bet::sole()->status)->toBe(BetStatus::Open)
        ->and($this->api->balanceOf($player))->toBe(90_000);
});

test('while the wallet has not answered the slip says it is checking, then shows the bet', function () {
    Queue::fake([ReconcileWager::class]);
    $player = $this->api->player(100_000);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');
    $this->api->next('place', 'lost-answer');

    $slip = slipFor($player, [slipPick($home)])->call('placeBet')->assertSee('Checking your bet…')->assertSee('wire:poll.3s="refreshPlaced"', false)
        ->assertNotDispatched('kadi-track'); // a bet the wallet may still refuse is not counted yet

    (new ReconcileWager('bet', Bet::sole()->id))->handle(app(WagerPlacement::class));

    $slip->call('refreshPlaced')->assertSee('Bet placed')->assertDontSee('Checking your bet…')
        ->assertDispatched('kadi-track', name: 'bet_placed', params: ['product' => 'sportsbook', 'bet_type' => 'single', 'legs' => 1, 'free_bet' => false, 'in_play' => false]);
});

test('too little money says so, with a way to deposit', function () {
    $player = $this->api->player(5_000);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    slipFor($player, [slipPick($home)])
        ->call('placeBet')
        ->assertHasErrors('bet')
        ->assertSee('Your balance is too low')
        ->assertSee("\$dispatch('open-deposit')", false);

    expect(Bet::sole()->status)->toBe(BetStatus::Rejected);
});

test('a stake above the balance the site knows is flagged, but the wallet decides: the button stays on', function () {
    $player = $this->api->player(100_000);
    Cache::put("wallet_balance_{$player->id}", 50.0, 60);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    $slip = slipFor($player, [slipPick($home)], '100')->assertSee("That's more than your wallet holds", false);

    expect(placeButtonDisabled($slip->html()))->toBeFalse();

    // The cached balance was stale (a deposit had landed): the wallet takes the bet.
    $slip->call('placeBet')->assertSee('Bet placed');
});

test('booking-code limits are per player when signed in, so a shared address never blocks others', function () {
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');
    $first = $this->api->player();

    foreach (range(1, 10) as $i) {
        slipFor($first, [slipPick($home)])->call('createBookingCode');
    }

    slipFor($first, [slipPick($home)])->call('createBookingCode')->assertHasErrors('booking');
    slipFor($this->api->player(), [slipPick($home)])->call('createBookingCode')->assertHasNoErrors();
});

test('the minimum stake is checked on the slip', function () {
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    $slip = slipFor($this->api->player(), [slipPick($home)], '5')->assertSee('The minimum stake is KES 10.');

    expect(placeButtonDisabled($slip->html()))->toBeTrue();
});

test('the slip says why a player cannot bet: switched off, unverified e-mail, or on a break', function (Closure $setup, string $message) {
    $player = $this->api->player();
    $setup($player, $this->api);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    slipFor($player->fresh(), [slipPick($home)])->assertSee($message)->assertDontSee('data-test="place-bet"', false);
})->with([
    'betting off' => [fn () => config(['platform.sports_betting' => false]), 'Betting opens soon'],
    'unverified' => [fn ($player) => $player->forceFill(['email_verified_at' => null])->save(), 'Verify your e-mail'],
    'on a break' => [function ($player, $api) {
        $api->breaks[$player->linked_id] = now()->addDays(3)->toIso8601String();
        app(ResponsiblePlay::class)->state($player);
    }, 'Betting is paused'],
]);

test('the odds-change choice is remembered on the account', function () {
    $player = $this->api->player();

    Livewire::actingAs($player)->test(BetSlip::class)->set('oddsPolicy', 'none');
    expect($player->fresh()->odds_change_policy)->toBe('none');

    Livewire::actingAs($player)->test(BetSlip::class)->assertSet('oddsPolicy', 'none')->set('oddsPolicy', 'nonsense')->assertSet('oddsPolicy', 'higher');
});

test('three selections show the multibet bonus; fewer say how many more earn it', function () {
    $picks = collect(range(1, 3))->map(fn () => slipPick(selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home')))->all();

    slipFor($this->api->player(), array_slice($picks, 0, 2))->assertSee('Add 1 more at odds');
    slipFor($this->api->player(), $picks)->assertSee('Multibet bonus')->assertSee('+3%');
});

test('a free bet can be the stake, and its terms are shown', function () {
    $player = $this->api->player(0);
    $freeBet = FreeBet::factory()->for($player)->create(['amount' => 10_000]);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    slipFor($player, [slipPick($home)], '')
        ->set('freeBetId', (string) $freeBet->id)
        ->assertSee('free bet is the stake')
        ->call('placeBet')
        ->assertSee('Bet placed');

    expect(Bet::sole()->free_bet_id)->toBe($freeBet->id)
        ->and($this->api->balanceOf($player))->toBe(0);
});

test('a booking code can be shared and loaded again', function () {
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    slipFor(null, [slipPick($home)])->call('createBookingCode')->assertSee('Booking code')->assertSee('WhatsApp');
    $code = BookingCode::sole()->code;

    Livewire::test(BetSlip::class)
        ->call('loadBookingCode', $code)
        ->assertDispatched('betslip-replace', fn (string $name, array $params) => $params['picks'][0]['selection_id'] === $home->id);
});

test('the open bets under the slip are the player\'s own', function () {
    $player = $this->api->player();
    betOn($player, [[pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home']]);
    betOn($this->api->player(), [[pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'away']]);

    $slip = Livewire::actingAs($player)->test(BetSlip::class)->assertSee('Open bets');

    expect($slip->instance()->openBets)->toHaveCount(1);
});

test('the jackpot page enters through the Kadi wallet', function () {
    $player = $this->api->player(100_000);
    $jackpot = openJackpot();
    $picks = $jackpot->jackpotFixtures->pluck('id')->mapWithKeys(fn ($id) => [$id => '1'])->all();

    Livewire::actingAs($player)->test(JackpotPage::class)
        ->assertSee($jackpot->name)
        ->call('enter', $picks)
        ->assertSee("You're in!", false)
        ->assertDispatched('wallet-refreshed');

    expect(JackpotEntry::sole()->status)->toBe(JackpotEntryStatus::Open)
        ->and($this->api->balanceOf($player))->toBe(90_100);
});

test('a jackpot entry the wallet refuses shows why', function () {
    $jackpot = openJackpot();
    $picks = $jackpot->jackpotFixtures->pluck('id')->mapWithKeys(fn ($id) => [$id => 'X'])->all();

    Livewire::actingAs($this->api->player(1_000))->test(JackpotPage::class)
        ->call('enter', $picks)
        ->assertHasErrors('entry')
        ->assertSee('too low');
});

test('the jackpot page asks guests to log in, and says when nothing is open', function () {
    $this->get('/sports/jackpot')->assertOk()->assertSee('No jackpot is open right now');

    openJackpot();

    $this->get('/sports/jackpot')->assertOk()->assertSee('Log in to enter');
});

test('activity lists the player\'s own sports bets: open, settled and jackpot entries', function () {
    $player = $this->api->player(100_000);
    $fixture = pricedFixture(['home_team' => 'Wolves', 'kicks_off_at' => now('UTC')->addHours(3)]);
    betOn($player, [[$fixture, 'home']]);
    betOn($this->api->player(100_000), [[pricedFixture(['home_team' => 'Fulham', 'kicks_off_at' => now('UTC')->addHours(3)]), 'home']]);

    $activity = Livewire::actingAs($player)->test(Activity::class)->call('show', 'sports');
    $activity->assertSee('Wolves')->assertDontSee('Fulham')->assertSee('Pays KES 210.00');

    recordScore($fixture, 2, 0);

    $activity->call('showSports', 'open')->assertSee('No open bets');
    $activity->call('showSports', 'settled')->assertSee('Wolves')->assertSee('Won KES 210.00')->assertSee('You won')->assertSee('FT 2-0');
    $activity->call('showSports', 'jackpot')->assertSee('No jackpot entries yet');
});

test('a settled multibet shows as a slip: every pick with its odds and result, and what the player lost', function () {
    $player = $this->api->player(100_000);
    $won = pricedFixture(['home_team' => 'Wolves', 'away_team' => 'Leeds', 'kicks_off_at' => now('UTC')->addHours(3)]);
    $lost = pricedFixture(['home_team' => 'Everton', 'away_team' => 'Burnley', 'kicks_off_at' => now('UTC')->addHours(4)]);
    $bet = betOn($player, [[$won, 'home'], [$lost, 'home']]);

    Livewire::actingAs($player)->test(Activity::class)->call('show', 'sports')
        ->assertSee('Multibet · 2 picks')->assertSee('Bet ID '.$bet->reference)
        ->assertSee('Wolves v Leeds')->assertSee('Everton v Burnley')->assertSee('2.10')
        ->assertSee('Possible payout (before tax)');

    recordScore($won, 2, 0);
    recordScore($lost, 0, 1);

    $html = Livewire::actingAs($player)->test(Activity::class)->call('show', 'sports')->call('showSports', 'settled')
        ->assertSee('Lost KES 100.00')->assertSee('You lost')->assertSee('FT 2-0')->assertSee('FT 0-1')
        ->html();

    expect(substr_count($html, 'class="nt-bet-badge nt-bet-badge-sm" data-status="won"'))->toBe(1)
        ->and(substr_count($html, 'class="nt-bet-badge nt-bet-badge-sm" data-status="lost"'))->toBe(1)
        ->and($html)->toContain('class="nt-bet-badge" data-status="lost"');
});

test('the bet list loads every leg, match and result in a fixed number of queries', function () {
    $player = $this->api->player(100_000);

    foreach (range(1, 3) as $i) {
        betOn($player, [[pricedFixture(['kicks_off_at' => now('UTC')->addHours($i)]), 'home'], [pricedFixture(['kicks_off_at' => now('UTC')->addHours($i + 5)]), 'away']]);
    }

    $component = Livewire::actingAs($player)->test(Activity::class)->call('show', 'sports');
    DB::enableQueryLog();
    $component->call('goTo', 1);
    $queries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'sb_'))->count();

    expect($queries)->toBeLessThanOrEqual(5);
});

test('the bet slip has a My bets tab with the player\'s open bets, and guests are asked to sign in', function () {
    $player = $this->api->player(100_000);
    betOn($player, [[pricedFixture(['home_team' => 'Wolves', 'kicks_off_at' => now('UTC')->addHours(3)]), 'home']]);

    Livewire::actingAs($player)->test(BetSlip::class)
        ->assertSeeHtml('data-test="slip-my-bets"')->assertSee('Open bets')->assertSee('Wolves')
        ->assertSeeHtml('href="'.route('activity', ['game' => 'sports']).'"');

    auth()->logout();

    expect(Livewire::test(BetSlip::class)->html())
        ->toMatch('#<a class="nt-slip-tab" href="'.preg_quote(route('activity', ['game' => 'sports']), '#').'" data-needs-account data-test="slip-my-bets">My bets</a>#');
});

test('My bets lists open and settled bets and opens each one in full inside the slip', function () {
    $player = User::factory()->create();
    $open = Bet::factory()->for($player)->create(['placed_at' => now()->subMinute()]);
    BetLeg::factory()->for($open)->create(['selection_name' => 'Wolves', 'market_name' => '1X2', 'fixture_name' => 'Wolves v Fulham', 'price' => '2.40']);
    $won = Bet::factory()->for($player)->create(['status' => BetStatus::Won, 'payout' => 20_000, 'placed_at' => now()->subDay()]);
    BetLeg::factory()->for($won)->create(['selection_name' => 'Everton', 'status' => BetLegStatus::Won, 'price' => '1.85']);
    $void = Bet::factory()->for($player)->create(['status' => BetStatus::Void, 'payout' => 10_000, 'placed_at' => now()->subDays(2)]);
    BetLeg::factory()->for($void)->create(['selection_name' => 'Brentford', 'status' => BetLegStatus::Void, 'void_reason' => 'Staff note: feed error']);
    $other = Bet::factory()->create();
    BetLeg::factory()->for($other)->create(['selection_name' => 'Someone Else FC']);

    $html = Livewire::actingAs($player)->test(BetSlip::class)->html();

    expect($html)
        ->toContain('Open bets')->toContain('Settled')
        ->toContain('x-on:click="showBet('.$open->id.')"')->toContain('x-on:click="showBet('.$won->id.')"')->toContain('x-on:click="showBet('.$void->id.')"')
        // Each bet's full slip is in place: picks, market, match, odds and the bet ID, never staff void reasons.
        ->toContain('x-show="bet === '.$open->id.'"')->toContain('Wolves v Fulham')->toContain('2.40')->toContain($open->reference)
        ->toContain('1.85')->toContain('Void · odds 1.00')
        ->not->toContain('Staff note')->not->toContain('Someone Else FC');
    expect(substr_count($html, 'data-test="slip-bet-detail"'))->toBe(3);
});

test('after a bet is placed, My bets opens it inside the slip instead of leaving the page', function () {
    $player = $this->api->player(100_000);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    $html = slipFor($player, [slipPick($home)])->call('placeBet')->assertSee('Bet placed')->html();
    $bet = Bet::query()->where('user_id', $player->id)->sole();

    expect($html)->toContain('data-test="placed-my-bets"')->toContain('x-on:click="showBet('.$bet->id.')"')
        ->toContain('x-show="bet === '.$bet->id.'"')
        ->not->toMatch('#<a[^>]*href="'.preg_quote(route('activity', ['game' => 'sports']), '#').'"[^>]*>My bets</a>#');
});

test('a multibet with one losing pick reads as lost: the pick is marked, the rest say not needed, and the reason is given', function () {
    $player = $this->api->player(100_000);
    $won = pricedFixture(['home_team' => 'Wolves', 'away_team' => 'Leeds', 'kicks_off_at' => now('UTC')->addHours(3)]);
    $lost = pricedFixture(['home_team' => 'Everton', 'away_team' => 'Burnley', 'kicks_off_at' => now('UTC')->addHours(4)]);
    $later = pricedFixture(['home_team' => 'Fulham', 'away_team' => 'Brentford', 'kicks_off_at' => now('UTC')->addDays(2)]);
    $bet = betOn($player, [[$won, 'home'], [$lost, 'home'], [$later, 'home']]);

    recordScore($won, 2, 0);
    recordScore($lost, 0, 1);

    expect($bet->fresh()->status)->toBe(BetStatus::Lost);

    $html = Livewire::actingAs($player)->test(BetSlip::class)->html();

    expect($html)
        ->toContain('Lost on Everton v Burnley (Everton). In a multibet every pick must win.')
        ->toContain('class="nt-bet-badge" data-status="lost"')
        ->toContain('class="nt-bet-leg" data-status="lost"')
        ->toContain('class="nt-bet-leg" data-status="won"')
        ->toContain('class="nt-bet-leg" data-status="moot"')->toContain('Not needed')
        ->toContain('1 of 3 picks lost')
        ->not->toContain('class="nt-bet-badge nt-bet-badge-sm" data-status="open"');
});

test('a bet whose pick lost reads as lost even before the bet itself is settled', function () {
    $bet = Bet::factory()->create();
    BetLeg::factory()->for($bet)->create(['status' => BetLegStatus::Lost, 'fixture_name' => 'Spurs v Leeds', 'selection_name' => 'Spurs']);
    BetLeg::factory()->for($bet)->create(['status' => BetLegStatus::Open]);

    $outcome = new BetOutcome($bet->load('legs'));

    expect($outcome->tone)->toBe('lost')
        ->and($outcome->label)->toBe('Lost')
        ->and($outcome->reason)->toBe('Lost on Spurs v Leeds (Spurs). In a multibet every pick must win.')
        ->and($outcome->legLabel($bet->legs[1], live: false))->toBe('Not needed');
});

test('an open multibet says how many picks won and how many are still to play', function () {
    $bet = Bet::factory()->create();
    BetLeg::factory()->for($bet)->create(['status' => BetLegStatus::Won]);
    BetLeg::factory()->for($bet)->create(['status' => BetLegStatus::Void]);
    BetLeg::factory()->for($bet)->create(['status' => BetLegStatus::Open]);

    $outcome = new BetOutcome($bet->load('legs'));

    expect($outcome->tone)->toBe('open')
        ->and($outcome->reason)->toBe('1 of 3 won, 1 to play. 1 void pick counted at odds 1.00.')
        ->and($outcome->legLabel($bet->legs[2], live: false))->toBe('Pending')
        ->and($outcome->legLabel($bet->legs[2], live: true))->toBe('Live');
});

test('the bet slip sheet closes on a page change and before the sign-in sheet opens', function () {
    $html = Livewire::test(BetSlip::class)->html();

    expect($html)->toMatch('#x-on:livewire:navigated\.document="[^"]*\$store\.betslip\.isOpen = false#')
        ->toContain('x-on:signin-opening.window="$store.betslip.isOpen = false"')
        ->toContain('x-on:open-signin.window="$store.betslip.isOpen = false"');
    expect(file_get_contents(resource_path('js/site/signin.js')))->toContain("new CustomEvent('signin-opening')");
});

test('My bets is one tap away from the sports pages and the menus', function () {
    $player = $this->api->player(100_000);
    pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $href = route('activity', ['game' => 'sports']);

    $page = $this->actingAs($player)->get('/sports')->assertOk()->assertSee('data-test="sports-my-bets"', false)->getContent();
    $nav = new SiteNavigation($player);

    expect(substr_count($page, 'href="'.$href.'"'))->toBeGreaterThanOrEqual(2) // sport sidebar and side menu
        ->and(collect($nav->accountMenu()[1])->pluck('href'))->toContain($href);

    config(['platform.sports' => false]);

    expect((new SiteNavigation($player))->sportsBetsLink())->toBeNull()
        ->and(collect((new SiteNavigation($player))->accountMenu()[1])->pluck('href'))->not->toContain($href);
});

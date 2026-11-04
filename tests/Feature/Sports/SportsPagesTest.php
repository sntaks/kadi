<?php

use App\Livewire\Account\Activity;
use App\Livewire\Sports\BetSlip;
use App\Livewire\Sports\FixtureList;
use App\Models\User;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Jackpot;
use Illuminate\Support\Facades\Date;

/*
 * Phase 9: the sports pages in the Kadi shell (plan §2). Prices you can tap everywhere, real counts, and the
 * match panel over the lists.
 */

beforeEach(function () {
    config(['platform.ui_v2' => true, 'platform.sports' => true]);
});

test('the sports pages exist only with the new look and sports switched on', function (array $flags) {
    config($flags);

    $this->get('/sports')->assertNotFound();
})->with([
    'sports off' => [['platform.sports' => false]],
    'new look off' => [['platform.ui_v2' => false]],
]);

test('/sports lists matches with tappable prices, grouped by day and competition, with the real count', function () {
    $fixture = pricedFixture(['home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'kicks_off_at' => now('UTC')->addHours(3)]);
    $home = selectionFor($fixture, 'home');

    $this->get('/sports')
        ->assertOk()
        ->assertSee('Arsenal')
        ->assertSee($fixture->competition->name)
        ->assertSee('1 of 1 match shown')
        ->assertSee('$store.betslip.toggle('.$fixture->id.', '.$home->id.', \'2.10\', $pickLabels)', false)
        ->assertSee('data-match="Arsenal v Chelsea"', false)
        ->assertSee('href="'.route('sports.match', $fixture).'"', false);
});

test('the list column follows the market switcher', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $fixture->markets()->create(['type' => 'btts', 'key' => 'btts', 'status' => 'open', 'source' => 'derived', 'bookmaker_count' => 10, 'priced_at' => now('UTC')])
        ->selections()->createMany([['outcome' => 'yes', 'name' => 'Yes', 'feed_price' => '1.80'], ['outcome' => 'no', 'name' => 'No', 'feed_price' => '1.95']]);

    Livewire::test(FixtureList::class)
        ->set('marketKey', 'btts')
        ->assertSee('1.95')
        ->assertSee('data-two', false)
        ->set('marketKey', 'nonsense')
        ->assertSet('marketKey', 'h2h');
});

test('a closed market or a kicked-off match shows no tappable price', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $fixture->matchResultMarket->update(['status' => MarketStatus::Suspended]);

    $html = Livewire::test(FixtureList::class)->html();

    expect($html)->not->toContain('$store.betslip.toggle(');
});

test('a competition page lists only its matches', function () {
    $mine = pricedFixture(['home_team' => 'Inter', 'kicks_off_at' => now('UTC')->addHours(3)]);
    pricedFixture(['home_team' => 'Lyon', 'kicks_off_at' => now('UTC')->addHours(3)]);

    $this->get(route('sports.competition', $mine->competition))
        ->assertOk()->assertSee('Inter')->assertDontSee('Lyon');
});

test('a competition can never take the address of another sports page', function () {
    $competition = Competition::factory()->create(['name' => 'Live', 'slug' => null]);

    expect($competition->slug)->not->toBe('live')->toStartWith('live-');
    $this->get('/sports/live')->assertOk()->assertSee('In play');
});

test('a match page shows every market, and closes betting once the match kicks off', function () {
    $fixture = pricedFixture(['home_team' => 'Napoli', 'away_team' => 'Roma', 'kicks_off_at' => now('UTC')->addHours(3)]);

    $this->get(route('sports.match', $fixture))->assertOk()->assertSee('Napoli')->assertSee('1X2')->assertSee('$store.betslip.toggle(', false);

    $this->travelTo($fixture->kicks_off_at->addMinute());

    $this->get(route('sports.match', $fixture))->assertOk()->assertSee('betting on it is closed')->assertDontSee('$store.betslip.toggle(', false);
});

test('a match of a competition no longer synced is not shown', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    $fixture->competition->update(['is_synced' => false]);

    $this->get(route('sports.match', $fixture))->assertNotFound();
    $this->get(route('sports.competition', $fixture->competition))->assertNotFound();
});

test('in play lists matches being played without prices, and says in-play betting is coming', function () {
    pricedFixture(['home_team' => 'Milan', 'kicks_off_at' => now('UTC')->subMinutes(20)]);

    $this->get('/sports/live')->assertOk()->assertSee('Milan')->assertSee('Betting closed')->assertSee('In-play betting is coming soon')
        ->assertDontSee('$store.betslip.toggle(', false);
});

test('search finds matches by team or competition', function () {
    pricedFixture(['home_team' => 'Everton', 'kicks_off_at' => now('UTC')->addHours(3)]);

    $this->get('/sports/search?q=ever')->assertOk()->assertSee('Everton');
    $this->get('/sports/search?q=zzz')->assertOk()->assertSee('No matches for');
});

test('the side menu lists Highlights with the real count, competitions with matches, and In play as Soon', function () {
    $fixture = pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);
    pricedFixture(['competition_id' => $fixture->competition_id, 'kicks_off_at' => now('UTC')->addDays(2)]);
    Competition::factory()->synced()->create(['name' => 'Empty League']);

    $this->get('/sports')
        ->assertSeeInOrder(['Highlights', '2', 'Today', $fixture->competition->name, '2', 'In play', 'Soon'])
        ->assertDontSee('Empty League');
});

test('the sports home shows the jackpot banner while it takes entries', function () {
    $jackpot = Jackpot::factory()->open()->withMatches(3)->create(['name' => 'Weekend Jackpot']);

    $this->get('/sports')->assertSee('Weekend Jackpot')->assertSee('Play the jackpot');

    $this->travelTo($jackpot->closesAt()->addMinute());

    $this->get('/sports')->assertDontSee('Play the jackpot');
});

test('the landing shows today\'s big match and the football list beside the Kadi table', function () {
    $this->travelTo(Date::parse('2026-11-07 06:00:00', 'UTC')); // 09:00 EAT
    $big = pricedFixture(['home_team' => 'Liverpool', 'away_team' => 'Everton', 'kicks_off_at' => Date::parse('2026-11-07 15:00:00', 'UTC')]);
    pricedFixture(['home_team' => 'Leeds', 'kicks_off_at' => Date::parse('2026-11-07 17:00:00', 'UTC')]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Kadi tables are open')
        ->assertSee("Today's big match")
        ->assertSee('Also kicking off today')
        ->assertSee('Football')
        ->assertSee('All 2 matches')
        ->assertSee('nt-has-rail', false);

    expect($big->id)->toBeInt();
});

test('without sports the landing keeps the Kadi table and no slip', function () {
    config(['platform.sports' => false]);
    pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);

    $this->get('/')->assertOk()->assertSee('Kadi tables are open')->assertDontSee("Today's big match")->assertDontSee('data-bet-slip', false);
});

test('the top bar search index lists competitions and matches while sports is on', function () {
    $fixture = pricedFixture(['home_team' => 'Brighton', 'kicks_off_at' => now('UTC')->addHours(3)]);

    $this->getJson(route('games.search'))
        ->assertOk()
        ->assertJsonFragment(['name' => 'Brighton v '.$fixture->away_team, 'url' => route('sports.match', $fixture)])
        ->assertJsonFragment(['name' => $fixture->competition->name]);
});

test('the search index stays closed while neither games nor sports are on', function () {
    config(['platform.sports' => false, 'platform.token_launch' => false]);

    $this->getJson(route('games.search'))->assertNotFound();
});

test('the bottom bar Bet slip button shows the slip count and opens the sheet', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/sports')
        ->assertSee("\$dispatch('open-bet-slip')", false)
        ->assertSee('$store.betslip.count()', false);
});

test('pages are shown in Nairobi time', function () {
    $this->travelTo(Date::parse('2026-11-07 06:00:00', 'UTC'));
    pricedFixture(['kicks_off_at' => Date::parse('2026-11-07 15:00:00', 'UTC')]);

    $this->get('/sports')->assertSee('18:00')->assertSee('Today ·');

    expect(Fixture::count())->toBe(1);
});

test('activity tabs keep working in the browser: no property shadows the show() action', function () {
    expect(property_exists(Activity::class, 'show'))->toBeFalse()
        ->and(method_exists(Activity::class, 'show'))->toBeTrue();
});

test('the side menu and the slip never show sports while the sports pages are off', function () {
    config(['platform.ui_v2' => false]);
    pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);

    $this->get('/faq')->assertOk()->assertDontSee('data-bet-slip', false)->assertDontSee('Highlights');
});

test('the side menu groups read Kadi, Casino, Sports and start collapsed, except the group of the current page', function () {
    config(['platform.token_launch' => true]);
    pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]);

    $faq = $this->get('/faq')->assertOk();
    $faq->assertSeeInOrder(['aria-controls="site-group-kadi"', 'aria-controls="site-group-casino"', 'aria-controls="site-group-sports"'], false);
    expect(substr_count($faq->getContent(), 'aria-expanded="false" x-bind:aria-expanded="open"'))->toBe(3);

    $sports = $this->get('/sports')->assertOk();
    $sports->assertSee('aria-expanded="true" x-bind:aria-expanded="open" aria-controls="site-group-sports"', false);
    expect(substr_count($sports->getContent(), 'aria-expanded="false" x-bind:aria-expanded="open"'))->toBe(2);
});

test('the docked slip sits under the notice strip, so its buttons are never covered', function () {
    $this->get('/sports')->assertSee('notice.getBoundingClientRect().bottom', false)->assertSee('max-height: calc(100dvh', false);
});

test('a signed-in player whose account is not linked yet is told so, not asked to verify', function () {
    $player = User::factory()->create(['linked_id' => null]);
    config(['platform.sports_betting' => true]);
    $home = selectionFor(pricedFixture(['kicks_off_at' => now('UTC')->addHours(3)]), 'home');

    Livewire::actingAs($player)->test(BetSlip::class)
        ->call('syncPicks', [slipPick($home)])
        ->assertSee('Your account is still being set up')
        ->assertDontSee('Verify your e-mail');
});

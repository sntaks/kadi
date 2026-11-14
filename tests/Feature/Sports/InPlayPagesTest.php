<?php

use App\Livewire\Sports\BetSlip;
use App\Livewire\Sports\MatchPage;
use App\Sportsbook\Enums\MarketSource;
use App\Sportsbook\Enums\MarketStatus;
use App\Sportsbook\Enums\MarketType;
use App\Sportsbook\Models\Competition;
use App\Sportsbook\Models\Fixture;
use App\Sportsbook\Models\Market;
use App\Sportsbook\Models\Selection;
use App\Sportsbook\Models\Sport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

/*
 * Plan §5 phase 5c: the pages for matches in play: /sports/live, the match page, the menu and the slip.
 */

beforeEach(function () {
    config(['platform.sports' => true, 'platform.sports_in_play' => true]);
    $this->travelTo(Date::parse('2026-11-21 15:30:00', 'UTC'));
    Cache::flush();

    $football = Sport::factory()->configured('football')->create();
    $epl = Competition::factory()->synced()->for($football)->create(['odds_api_key' => 'soccer_epl', 'name' => 'EPL']);
    $this->live = Fixture::factory()->for($epl)->create([
        'home_team' => 'Arsenal', 'away_team' => 'Chelsea', 'kicks_off_at' => now('UTC')->subMinutes(30), 'home_score' => 2, 'away_score' => 1,
    ]);
    $this->winner = Market::factory()->for($this->live)->create(['source' => MarketSource::Feed, 'status' => MarketStatus::Open, 'priced_at' => now('UTC')]);
    $this->home = Selection::factory()->for($this->winner)->create(['outcome' => 'home', 'name' => 'Arsenal', 'feed_price' => '1.30']);
    Selection::factory()->for($this->winner)->create(['outcome' => 'draw', 'name' => 'Draw', 'feed_price' => '4.80']);
    Selection::factory()->for($this->winner)->create(['outcome' => 'away', 'name' => 'Chelsea', 'feed_price' => '9.00']);

    // A competition that is not on the in-play list: in play, but betting closed.
    $spl = Competition::factory()->synced()->for($football)->create(['odds_api_key' => 'soccer_spl', 'name' => 'Premiership']);
    $this->other = Fixture::factory()->for($spl)->create(['home_team' => 'Celtic', 'away_team' => 'Rangers', 'kicks_off_at' => now('UTC')->subMinutes(20)]);
    Market::factory()->for($this->other)->create(['source' => MarketSource::Feed, 'status' => MarketStatus::Open, 'priced_at' => now('UTC')]);
});

test('/sports/live shows each match in play with its score, and live prices only where we trade them', function () {
    $html = $this->get('/sports/live')->assertOk()
        ->assertSee('Live bets are checked for a few seconds')
        ->assertSeeInOrder(['Arsenal', '2', 'Chelsea', '1'])
        ->assertSee('Celtic')
        ->assertSee('Betting closed')
        ->assertDontSee('In-play betting is coming soon')
        ->getContent();

    expect($html)->toContain('aria-label="Arsenal at 1.30"')
        ->and($html)->not->toContain('Celtic at');
});

test('with in play off, /sports/live is a list only and the menu says Soon', function () {
    config(['platform.sports_in_play' => false]);

    $this->get('/sports/live')->assertOk()
        ->assertSee('In-play betting is coming soon')
        ->assertDontSee('aria-label="Arsenal at 1.30"', false)
        ->assertSeeInOrder(['In play', 'Soon']);
});

test('the menu shows the real number of matches in play while in play is on', function () {
    $this->get('/sports')->assertOk()->assertSeeInOrder(['In play', '2'])->assertDontSee('>Soon<', false);
});

test('a match finished by the feed leaves the live page', function () {
    $this->live->update(['feed_completed_at' => now('UTC')]);

    $this->get('/sports/live')->assertOk()->assertDontSee('Arsenal');
});

test('the match page shows the live score, the live note and tappable live prices; derived markets stay closed', function () {
    $btts = Market::factory()->for($this->live)->create(['type' => MarketType::BothTeamsToScore, 'key' => 'btts', 'source' => MarketSource::Derived, 'status' => MarketStatus::Open, 'priced_at' => now('UTC')]);
    Selection::factory()->for($btts)->create(['outcome' => 'yes', 'name' => 'Yes', 'feed_price' => '1.40']);

    $html = $this->get(route('sports.match', $this->live))->assertOk()
        ->assertSeeHtml('data-test="live-score"')
        ->assertSee('2 – 1')
        ->assertSeeHtml('data-test="live-note"')
        ->assertDontSee('betting on it is closed')
        ->getContent();
    expect($html)->toContain('aria-label="Arsenal at 1.30"')->and($html)->toContain('wire:poll.15s.visible');

    Livewire::test(MatchPage::class, ['fixture' => $this->live])->set('tab', 'goals')->assertSee('Yes: betting closed');
});

test('past the late phase, or with in play off, the match page says betting is closed', function () {
    $this->travelTo(now()->addMinutes(56));
    $this->get(route('sports.match', $this->live))->assertSee('This match has started, so betting on it is closed.')->assertSee('2 – 1');

    $this->travelTo(Date::parse('2026-11-21 15:30:00', 'UTC'));
    config(['platform.sports_in_play' => false]);
    $this->get(route('sports.match', $this->live))->assertSee('This match has started, so betting on it is closed.');
});

test('a live tick redraws the match page only when it carries this match', function () {
    $page = Livewire::test(MatchPage::class, ['fixture' => $this->live]);
    $this->home->update(['feed_price' => '1.25']);

    $page->call('refreshLive', ['fixtures' => [['id' => $this->other->id]]])->assertDontSee('Arsenal at 1.25');
    $page->call('refreshLive', ['fixtures' => [['id' => $this->live->id]]])->assertSee('Arsenal at 1.25');
});

test('a live pick is tagged Live in the bet slip', function () {
    Livewire::test(BetSlip::class)->call('syncPicks', [slipPick($this->home)])
        ->assertSeeHtml('data-test="live-pick"')
        ->assertSee("Live bets are checked for a few seconds before they're placed.", false);
});
